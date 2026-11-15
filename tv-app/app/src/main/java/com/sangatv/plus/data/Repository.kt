package com.sangatv.plus.data

import android.util.Log
import com.sangatv.plus.BuildConfig
import com.sangatv.plus.data.local.CacheStore
import com.sangatv.plus.data.local.PinGuard
import com.sangatv.plus.data.local.SecureStore
import com.sangatv.plus.data.model.AckRequest
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

    /** Token revoked or rejected: forget it and show the provisioning screen. */
    private fun deprovision() {
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
    }

    // ---------------- Content ----------------

    suspend fun refreshAll(): Boolean = refreshLock.withLock {
        if (secure.token == null) return false
        try {
            val b = api.bootstrap()
            _bootstrap.value = b
            save(BOOTSTRAP, Bootstrap.serializer(), b)
            kiosk.configure(b.apps.map { it.packageName } + com.sangatv.plus.device.TvDefaults.hdmiHandlerPackages(appContext))
            com.sangatv.plus.device.ScreenTimeout.apply(appContext, b.config.screensaverMinutes)

            refreshChannelsLocked()

            val f = api.facilities()
            _facilities.value = f
            save(FACILITIES, FacilitiesResponse.serializer(), f)

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
                beat()
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
                ),
            )
            _online.value = true
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
    }
}
