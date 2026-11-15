package com.sangatv.plus.data

import android.util.Log
import com.sangatv.plus.BuildConfig
import com.sangatv.plus.data.local.CacheStore
import com.sangatv.plus.data.local.PinGuard
import com.sangatv.plus.data.local.SecureStore
import com.sangatv.plus.data.model.AckRequest
import com.sangatv.plus.data.model.AnnouncementDto
import com.sangatv.plus.data.model.EpgResponse
import com.sangatv.plus.data.model.FavoritesRequest
import com.sangatv.plus.data.model.ViewDto
import com.sangatv.plus.data.model.ViewsRequest
import com.sangatv.plus.data.model.Bootstrap
import com.sangatv.plus.data.model.ChannelsResponse
import com.sangatv.plus.data.model.CommandDto
import com.sangatv.plus.data.model.FacilitiesResponse
import com.sangatv.plus.data.model.HeartbeatRequest
import com.sangatv.plus.data.model.OfflineAttempts
import com.sangatv.plus.data.model.PinVerifyRequest
import com.sangatv.plus.data.model.PinVerifyResponse
import com.sangatv.plus.data.model.ProvisionRequest
import com.sangatv.plus.data.model.TechnicianEvent
import com.sangatv.plus.data.remote.ApiClient
import com.sangatv.plus.device.AutoStart
import com.sangatv.plus.device.KioskManager
import com.sangatv.plus.device.OtaUpdater
import com.sangatv.plus.device.ServiceState
import kotlinx.coroutines.CoroutineScope
import kotlinx.coroutines.Job
import kotlinx.coroutines.delay
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.isActive
import kotlinx.coroutines.launch
import kotlinx.coroutines.sync.Mutex
import kotlinx.coroutines.sync.withLock
import kotlinx.serialization.KSerializer
import kotlinx.serialization.json.JsonPrimitive
import kotlinx.serialization.json.buildJsonObject
import kotlinx.serialization.json.jsonArray
import kotlinx.serialization.json.jsonPrimitive
import retrofit2.HttpException
import java.io.IOException
import java.time.Instant

/**
 * Single source of truth for the launcher. Serves cached data immediately,
 * refreshes from the server, runs the heartbeat loop and executes server commands.
 */
class Repository(
    private val appContext: android.content.Context,
    private val client: ApiClient,
    private val secure: SecureStore,
    private val cache: CacheStore,
    val pinGuard: PinGuard,
    val kiosk: KioskManager,
    private val ota: OtaUpdater,
    private val scope: CoroutineScope,
) {
    private val api = client.api
    private val json = client.json

    private val _bootstrap = MutableStateFlow(load(BOOTSTRAP, Bootstrap.serializer()))
    val bootstrap: StateFlow<Bootstrap?> = _bootstrap.asStateFlow()

    private val _channels = MutableStateFlow(load(CHANNELS, ChannelsResponse.serializer()))
    val channels: StateFlow<ChannelsResponse?> = _channels.asStateFlow()

    private val _facilities = MutableStateFlow(load(FACILITIES, FacilitiesResponse.serializer()))
    val facilities: StateFlow<FacilitiesResponse?> = _facilities.asStateFlow()

    private val _epg = MutableStateFlow(load(EPG, EpgResponse.serializer()))
    val epg: StateFlow<EpgResponse?> = _epg.asStateFlow()

    private val _announcements = MutableStateFlow(_bootstrap.value?.announcements.orEmpty())
    val announcements: StateFlow<List<AnnouncementDto>> = _announcements.asStateFlow()

    /** Channel number the TV must switch to (announcement with "pindahkan ke channel"); consumed by the UI. */
    private val _forceChannel = MutableStateFlow<Int?>(null)
    val forceChannel: StateFlow<Int?> = _forceChannel.asStateFlow()

    private val _favorites = MutableStateFlow(_bootstrap.value?.favorites.orEmpty())
    val favorites: StateFlow<List<Long>> = _favorites.asStateFlow()

    private val viewer = appContext.getSharedPreferences("viewer", android.content.Context.MODE_PRIVATE)
    private val pendingViews = java.util.concurrent.ConcurrentLinkedQueue<ViewDto>()
    private var lastEpgFetch = 0L

    private val _online = MutableStateFlow(false)
    val online: StateFlow<Boolean> = _online.asStateFlow()

    private val _provisioned = MutableStateFlow(secure.token != null)
    val provisioned: StateFlow<Boolean> = _provisioned.asStateFlow()

    val deviceUid: String get() = secure.deviceUid
    val isPinned: Boolean get() = client.isPinned
    val apiHost: String get() = client.baseUrl

    private val refreshLock = Mutex()
    private var loopJob: Job? = null
    private var lastContentVersion: String? = null
    private var lastOtaCheck = 0L

    private fun <T> load(name: String, ser: KSerializer<T>): T? =
        cache.read(name)?.let { runCatching { json.decodeFromString(ser, it) }.getOrNull() }

    private fun <T> save(name: String, ser: KSerializer<T>, value: T) = cache.write(name, json.encodeToString(ser, value))

    // ---------------- Provisioning ----------------

    suspend fun provision(code: String): Result<String> = runCatching {
        val res = api.provision(
            ProvisionRequest(
                code = code.trim(),
                deviceUid = secure.deviceUid,
                model = "${android.os.Build.MANUFACTURER} ${android.os.Build.MODEL}",
                androidVersion = android.os.Build.VERSION.RELEASE,
                appVersion = BuildConfig.VERSION_NAME,
                appVersionCode = BuildConfig.VERSION_CODE.toLong(),
                deviceOwner = kiosk.isDeviceOwner,
            ),
        )
        secure.token = res.token
        _provisioned.value = true
        if (ServiceState.reason == ServiceState.Reason.Revoked) ServiceState.restore(appContext)
        kiosk.harden()
        refreshAll()
        start()
        res.device.room
    }.recoverCatching { e ->
        throw IllegalStateException(
            when {
                e is HttpException && e.code() == 422 -> "kode tidak valid atau kedaluwarsa"
                e is HttpException && e.code() == 429 -> "terlalu banyak percobaan, tunggu sebentar"
                e is IOException -> "server tidak terjangkau"
                else -> e.message ?: "kesalahan tidak diketahui"
            },
        )
    }

    /**
     * Token revoked or rejected: forget it. A TV that was registered before is treated as removed from
     * the hotel system and goes to Mode lepas (neutral launcher) instead of trapping guests on the
     * registration screen; the technician can still register it again from there.
     */
    private fun deprovision() {
        if (secure.token != null) ServiceState.release(appContext, ServiceState.Reason.Revoked)
        secure.token = null
        _provisioned.value = false
        loopJob?.cancel()
    }

    fun resetRegistration() {
        deprovision()
        cache.clear()
        _bootstrap.value = null
        _channels.value = null
        _facilities.value = null
        _epg.value = null
        _favorites.value = emptyList()
        _announcements.value = emptyList()
    }

    // ---------------- Content ----------------

    suspend fun refreshAll(): Boolean = refreshLock.withLock {
        if (secure.token == null) return false
        try {
            val b = api.bootstrap()
            _bootstrap.value = b
            save(BOOTSTRAP, Bootstrap.serializer(), b)
            _favorites.value = b.favorites
            ServiceState.markContact(appContext)
            ServiceState.onServerStatus(appContext, b.service?.status)
            onAnnouncements(b.announcements)
            kiosk.configure(b.apps.map { it.packageName } + com.sangatv.plus.device.TvDefaults.hdmiHandlerPackages(appContext))
            com.sangatv.plus.device.ScreenTimeout.apply(appContext, b.config.screensaverMinutes)

            refreshChannelsLocked()

            val f = api.facilities()
            _facilities.value = f
            save(FACILITIES, FacilitiesResponse.serializer(), f)

            refreshEpgLocked()

            _online.value = true
            true
        } catch (e: Exception) {
            handleError(e)
            false
        }
    }

    suspend fun refreshChannels() = refreshLock.withLock {
        try {
            refreshChannelsLocked()
        } catch (e: Exception) {
            handleError(e)
        }
    }

    private suspend fun refreshChannelsLocked() {
        val c = api.channels()
        _channels.value = c
        save(CHANNELS, ChannelsResponse.serializer(), c)
    }

    /** Programme guide: refetched with the content and at least every hour. */
    private suspend fun refreshEpgLocked() {
        // 48 h: the guide keeps working through a long internet outage.
        runCatching { api.epg(48) }
            .onSuccess { e ->
                _epg.value = e
                save(EPG, EpgResponse.serializer(), e)
                lastEpgFetch = System.currentTimeMillis()
            }
            .onFailure { Log.w(TAG, "epg: ${it.message}") }
    }

    private suspend fun maybeRefreshEpg() {
        if (System.currentTimeMillis() - lastEpgFetch > 60 * 60_000L) refreshLock.withLock { refreshEpgLocked() }
    }

    // ---------------- Favourites, viewing stats, announcements ----------------

    /** Toggle a favourite locally right away and sync it to the guest's stay (409 = no guest: keep it locally). */
    fun toggleFavorite(channelId: Long) {
        val now = _favorites.value
        val next = if (channelId in now) now - channelId else now + channelId
        _favorites.value = next
        if (secure.token == null) return
        scope.launch { runCatching { api.favorites(FavoritesRequest(next)) } }
    }

    /** One viewing session; sent in batches with the heartbeat. Zapping (< 10 s) is dropped. */
    fun recordView(channelId: Long, startedAtMs: Long, seconds: Long) {
        if (seconds < 10) return
        pendingViews.add(ViewDto(channelId, Instant.ofEpochMilli(startedAtMs).toString(), seconds.coerceAtMost(6 * 3600)))
        while (pendingViews.size > 300) pendingViews.poll()
    }

    private suspend fun flushViews() {
        if (pendingViews.isEmpty()) return
        val batch = generateSequence { pendingViews.poll() }.take(100).toList()
        runCatching { api.views(ViewsRequest(batch)) }.onFailure { batch.forEach(pendingViews::add) }
    }

    private fun onAnnouncements(list: List<AnnouncementDto>) {
        _announcements.value = list
        // "Pindahkan ke channel": once per announcement version, only while the screen is on.
        val screenOn = appContext.getSystemService(android.os.PowerManager::class.java)?.isInteractive ?: true
        val done = viewer.getStringSet(FORCED, emptySet()).orEmpty()
        val fresh = list.firstOrNull { it.forceChannelNumber != null && it.version !in done }
        if (fresh != null && screenOn) {
            viewer.edit().putStringSet(FORCED, (done + fresh.version).toList().takeLast(50).toSet()).apply()
            _forceChannel.value = fresh.forceChannelNumber
        }
        // Emergency messages and forced channels come to the front even over other apps (Netflix, HDMI).
        val urgent = fresh != null || list.any { it.isEmergency && it.version !in viewer.getStringSet(SURFACED, emptySet()).orEmpty() }
        if (urgent && screenOn && !AutoStart.isAppForeground) {
            viewer.edit().putStringSet(SURFACED, list.filter { it.isEmergency }.map { it.version }.toSet()).apply()
            AutoStart.openMain(appContext, goHome = false, debounceMs = 0)
        }
    }

    fun consumeForceChannel() {
        _forceChannel.value = null
    }

    private fun handleError(e: Exception) {
        when {
            e is HttpException && (e.code() == 401 || e.code() == 403) -> deprovision()
            e is IOException || e is HttpException -> _online.value = false
            else -> Log.w(TAG, "unexpected", e)
        }
        Log.w(TAG, "request failed: ${e.message}")
    }

    // ---------------- Heartbeat & commands ----------------

    fun start() {
        if (secure.token == null || loopJob?.isActive == true) return
        loopJob = scope.launch {
            refreshAll()
            while (isActive && secure.token != null) {
                ServiceState.checkOffline(appContext)
                beat()
                flushViews()
                maybeRefreshEpg()
                maybeRefreshSignedUrls()
                maybeCheckOta()
                delay((_bootstrap.value?.config?.heartbeatSeconds ?: 60).coerceIn(15, 600) * 1000L)
            }
        }
    }

    /** Fire-and-forget playback problem report (the server de-duplicates). */
    fun reportChannel(channelId: Long, status: String, message: String?) {
        if (secure.token == null) return
        scope.launch { runCatching { api.reportChannel(channelId, com.sangatv.plus.data.model.ChannelReportRequest(status, message?.take(255))) } }
    }

    /** Report immediately (e.g. right after the screen turns off/on) instead of waiting for the next tick. */
    fun beatNow() {
        if (secure.token == null) return
        scope.launch { beat() }
    }

    private suspend fun beat() {
        try {
            val res = api.heartbeat(
                HeartbeatRequest(
                    appVersion = BuildConfig.VERSION_NAME,
                    appVersionCode = BuildConfig.VERSION_CODE.toLong(),
                    kioskEnabled = kiosk.isInLockTask,
                    deviceOwner = kiosk.isDeviceOwner,
                    isDefaultHome = AutoStart.isDefaultHome(appContext),
                    canDrawOverlays = AutoStart.canDrawOverlays(appContext),
                    canInstallUpdates = AutoStart.canInstallUpdates(appContext),
                    stockLauncherActive = AutoStart.isStockLauncherActive(appContext),
                    screenOn = appContext.getSystemService(android.os.PowerManager::class.java)?.isInteractive ?: true,
                    serviceReleased = ServiceState.isReleased,
                    appPackage = BuildConfig.APPLICATION_ID,
                ),
            )
            _online.value = true
            ServiceState.markContact(appContext)
            ServiceState.onServerStatus(appContext, res.service?.status)
            res.announcements?.let { if (!ServiceState.isReleased) onAnnouncements(it) }
            if (lastContentVersion != null && lastContentVersion != res.contentVersion) refreshAll()
            lastContentVersion = res.contentVersion
            res.commands.forEach { execute(it) }
            flushOfflinePinAttempts()
        } catch (e: Exception) {
            handleError(e)
        }
    }

    private suspend fun execute(cmd: CommandDto) {
        val (status, result) = try {
            when (cmd.type) {
                "clear_app_data" -> {
                    val pkgs = cmd.payload?.get("packages")?.jsonArray?.map { it.jsonPrimitive.content }.orEmpty()
                    val res = kiosk.clearAppData(pkgs)
                    val ok = res.values.all { it } || !kiosk.isDeviceOwner
                    (if (ok) "done" else "failed") to buildJsonObject {
                        res.forEach { (k, v) -> put(k, JsonPrimitive(v)) }
                        put("device_owner", JsonPrimitive(kiosk.isDeviceOwner))
                    }
                }
                "refresh" -> (if (refreshAll()) "done" else "failed") to null
                // CMS → Update Aplikasi → "Minta semua TV update sekarang" (instead of waiting for the 6-hour check).
                "check_update" -> {
                    lastOtaCheck = System.currentTimeMillis()
                    "done" to buildJsonObject { put("result", JsonPrimitive(ota.checkAndInstall())) }
                }
                "hide_stock_launcher", "show_stock_launcher" -> {
                    val res = kiosk.setStockLaunchersHidden(cmd.type == "hide_stock_launcher")
                    (if (kiosk.isDeviceOwner && res.isNotEmpty()) "done" else "failed") to buildJsonObject {
                        res.forEach { (k, v) -> put(k, JsonPrimitive(v)) }
                        if (!kiosk.isDeviceOwner) put("error", JsonPrimitive("TV bukan Device Owner; gunakan skrip siapkan-tv (ADB)"))
                    }
                }
                "reboot" -> {
                    if (!kiosk.isDeviceOwner) {
                        "failed" to buildJsonObject {
                            put("error", JsonPrimitive("Restart jarak jauh hanya untuk TV Device Owner"))
                            put("device_owner", JsonPrimitive(false))
                        }
                    } else {
                        // Ack first: once the reboot starts there is no chance to report.
                        api.ack(cmd.id, AckRequest("done", buildJsonObject { put("rebooting", JsonPrimitive(true)) }))
                        kiosk.reboot()
                        return
                    }
                }
                else -> "failed" to buildJsonObject { put("error", JsonPrimitive("unknown command")) }
            }
        } catch (e: Exception) {
            "failed" to buildJsonObject { put("error", JsonPrimitive(e.message ?: "error")) }
        }
        runCatching { api.ack(cmd.id, AckRequest(status, result)) }
    }

    /** Provider URLs carry short-lived tokens; refetch shortly before they expire. */
    private suspend fun maybeRefreshSignedUrls() {
        val exp = _channels.value?.expiresAt ?: return
        val expiresAt = runCatching { Instant.parse(exp) }.getOrNull()
            ?: runCatching { java.time.OffsetDateTime.parse(exp).toInstant() }.getOrNull() ?: return
        if (Instant.now().plusSeconds(120).isAfter(expiresAt)) refreshChannels()
    }

    private suspend fun maybeCheckOta() {
        val now = System.currentTimeMillis()
        if (now - lastOtaCheck < 6 * 3600_000L) return
        lastOtaCheck = now
        runCatching { ota.checkAndInstall() }.onSuccess { Log.i(TAG, "OTA: $it") }
    }

    suspend fun checkUpdateNow(): String = runCatching { ota.checkAndInstall() }.getOrElse { "Gagal cek update: ${it.message}" }

    // ---------------- Technician PIN ----------------

    /** Server is authoritative (lockout + audit log); falls back to the cached hash offline. */
    suspend fun verifyPin(pin: String): PinGuard.Result {
        if (pinGuard.isLocked()) return PinGuard.Result.Locked(pinGuard.lockedUntilMs)
        return try {
            val res = api.verifyPin(PinVerifyRequest(pin))
            val body: PinVerifyResponse? = res.body()
                ?: res.errorBody()?.string()?.let { runCatching { json.decodeFromString(PinVerifyResponse.serializer(), it) }.getOrNull() }
            when {
                res.code() == 401 || res.code() == 403 -> pinGuard.verifyOffline(pin, _bootstrap.value?.technicianPin)
                body?.ok == true -> { pinGuard.resetFailures(); PinGuard.Result.Ok }
                body?.locked == true -> {
                    val until = System.currentTimeMillis() + (body.retryAfter ?: 900) * 1000L
                    pinGuard.lockUntil(until)
                    PinGuard.Result.Locked(until)
                }
                body?.remaining != null -> { pinGuard.registerFailure(); PinGuard.Result.Wrong(body.remaining) }
                else -> pinGuard.verifyOffline(pin, _bootstrap.value?.technicianPin)
            }
        } catch (e: IOException) {
            pinGuard.verifyOffline(pin, _bootstrap.value?.technicianPin)
        }
    }

    /** Fire-and-forget audit entry for technician actions (Settings opened, launcher restore, …). */
    fun logTechnicianEvent(event: String, detail: String? = null) {
        scope.launch { runCatching { api.technicianEvent(TechnicianEvent(event, detail)) } }
    }

    private suspend fun flushOfflinePinAttempts() {
        val pending = pinGuard.pending()
        if (pending.isEmpty()) return
        runCatching { api.reportOfflineAttempts(OfflineAttempts(pending)) }.onSuccess { pinGuard.clearPending() }
    }

    private companion object {
        const val TAG = "Repository"
        const val BOOTSTRAP = "bootstrap"
        const val CHANNELS = "channels"
        const val FACILITIES = "facilities"
        const val EPG = "epg"
        const val FORCED = "forced_announcements"
        const val SURFACED = "surfaced_announcements"
    }
}
