package com.sangatv.plus.ui.screens

import android.app.Activity
import android.content.Intent
import android.provider.Settings
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.heightIn
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.runtime.Composable
import androidx.compose.runtime.DisposableEffect
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.rememberCoroutineScope
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.focus.FocusRequester
import androidx.compose.ui.focus.focusRequester
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.input.key.KeyEventType
import androidx.compose.ui.input.key.nativeKeyCode
import androidx.compose.ui.input.key.key
import androidx.compose.ui.input.key.onPreviewKeyEvent
import androidx.compose.ui.input.key.type
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.text.style.TextAlign
import androidx.tv.material3.Text
import com.sangatv.plus.BuildConfig
import com.sangatv.plus.R
import com.sangatv.plus.data.local.PinGuard
import com.sangatv.plus.device.AutoStart
import com.sangatv.plus.device.KioskManager
import com.sangatv.plus.ui.AppViewModel
import com.sangatv.plus.ui.components.Focusable
import com.sangatv.plus.ui.components.SIcon
import com.sangatv.plus.ui.components.SIcons
import com.sangatv.plus.ui.components.card
import com.sangatv.plus.ui.theme.Sanga
import com.sangatv.plus.ui.theme.body
import com.sangatv.plus.ui.theme.px
import kotlinx.coroutines.launch

@Composable
fun TechnicianScreen(vm: AppViewModel, roomNumber: String?, online: Boolean, onExit: () -> Unit) {
    var unlocked by remember { mutableStateOf(false) }
    Row(
        Modifier.fillMaxSize().background(Sanga.Background).padding(80.px),
        horizontalArrangement = Arrangement.spacedBy(72.px, Alignment.CenterHorizontally),
        verticalAlignment = Alignment.CenterVertically,
    ) {
        if (unlocked) TechActions(vm, onExit) else PinPad(vm) { unlocked = true }
        DeviceStatus(vm, roomNumber, online)
    }
}

@Composable
private fun PinPad(vm: AppViewModel, onUnlocked: () -> Unit) {
    val scope = rememberCoroutineScope()
    var pin by remember { mutableStateOf("") }
    var msg by remember { mutableStateOf<String?>(null) }
    var isError by remember { mutableStateOf(false) }
    var busy by remember { mutableStateOf(false) }
    val ctx = LocalContext.current

    fun submit() {
        if (busy) return
        if (pin.length < 6) { msg = ctx.getString(R.string.tech_pin_len); isError = true; return }
        busy = true
        val entered = pin
        pin = ""
        scope.launch {
            when (val r = vm.verifyPin(entered)) {
                PinGuard.Result.Ok -> onUnlocked()
                PinGuard.Result.NoPin -> { msg = ctx.getString(R.string.tech_no_pin); isError = true }
                is PinGuard.Result.Wrong -> { msg = ctx.getString(R.string.tech_pin_wrong, r.remaining); isError = true }
                is PinGuard.Result.Locked -> {
                    val mins = ((r.untilMs - System.currentTimeMillis()) / 60_000 + 1).coerceAtLeast(1)
                    msg = ctx.getString(R.string.tech_locked, mins.toInt()); isError = true
                }
            }
            busy = false
        }
    }

    fun press(label: String) {
        when (label) {
            "DEL" -> { pin = pin.dropLast(1); msg = null }
            "OK" -> submit()
            else -> if (pin.length < 6) { pin += label; msg = null }
        }
    }

    val first = remember { FocusRequester() }
    LaunchedEffect(Unit) { runCatching { first.requestFocus() } }

    Column(
        Modifier.width(620.px).card(RoundedCornerShape(32.px), borderWidth = 1.px).padding(56.px)
            .onPreviewKeyEvent { e ->
                // Remote number keys type directly.
                val code = e.key.nativeKeyCode
                if (e.type == KeyEventType.KeyDown && code in android.view.KeyEvent.KEYCODE_0..android.view.KeyEvent.KEYCODE_9) {
                    press((code - android.view.KeyEvent.KEYCODE_0).toString()); true
                } else false
            },
        horizontalAlignment = Alignment.CenterHorizontally,
        verticalArrangement = Arrangement.spacedBy(28.px),
    ) {
        Box(Modifier.size(96.px).background(Sanga.Surface2, RoundedCornerShape(28.px)), contentAlignment = Alignment.Center) {
            SIcon(SIcons.Lock, Sanga.Accent, 48.px)
        }
        Column(horizontalAlignment = Alignment.CenterHorizontally) {
            Text(stringResource(R.string.tech_title), style = body(44, 800))
            Text(stringResource(R.string.tech_subtitle), style = body(22, 400, Sanga.Text3), textAlign = TextAlign.Center, modifier = Modifier.padding(top = 10.px))
        }
        Row(horizontalArrangement = Arrangement.spacedBy(20.px), modifier = Modifier.padding(vertical = 8.px)) {
            repeat(6) { i ->
                Box(Modifier.size(26.px).border(2.px, Color(0xFF6F94B3), CircleShape).background(if (i < pin.length) Sanga.Accent else Color.Transparent, CircleShape))
            }
        }
        val rows = listOf(listOf("1", "2", "3"), listOf("4", "5", "6"), listOf("7", "8", "9"), listOf("DEL", "0", "OK"))
        Column(verticalArrangement = Arrangement.spacedBy(16.px)) {
            rows.forEachIndexed { r, row ->
                Row(horizontalArrangement = Arrangement.spacedBy(16.px)) {
                    row.forEachIndexed { c, label ->
                        val isOk = label == "OK"
                        Focusable(
                            onClick = { press(label) },
                            modifier = Modifier.weight(1f).then(if (r == 0 && c == 0) Modifier.focusRequester(first) else Modifier),
                        ) { focused ->
                            Box(
                                Modifier.fillMaxWidth().height(84.px)
                                    .background(if (isOk) Sanga.Accent else if (focused) Sanga.Border2 else Sanga.Surface2, RoundedCornerShape(20.px))
                                    .border(if (focused) 3.px else 1.px, if (focused) Sanga.Accent else Color(0xFF1F4A70), RoundedCornerShape(20.px)),
                                contentAlignment = Alignment.Center,
                            ) {
                                Text(
                                    if (label == "DEL") stringResource(R.string.tech_delete) else label,
                                    style = body(if (label.length > 1) 22 else 32, 700, if (isOk) Sanga.OnAccent else Sanga.Text),
                                )
                            }
                        }
                    }
                }
            }
        }
        Text(msg ?: "", style = body(20, 600, if (isError) Sanga.Error else Sanga.Text3), modifier = Modifier.heightIn(min = 30.px))
    }
}

@Composable
private fun TechActions(vm: AppViewModel, onExit: () -> Unit) {
    val ctx = LocalContext.current
    val activity = ctx as? Activity
    val kiosk = vm.kiosk
    var msg by remember { mutableStateOf<String?>(null) }
    var hardened by remember { mutableStateOf(kiosk.isHardened) }
    var confirmReset by remember { mutableStateOf(false) }
    val apps = vm.bootstrap.value?.apps.orEmpty().map { it.packageName }
    // RoleManager's request dialog needs startActivityForResult (it checks the calling package).
    var isHome by remember { mutableStateOf(AutoStart.isDefaultHome(ctx)) }
    val roleLauncher = androidx.activity.compose.rememberLauncherForActivityResult(
        androidx.activity.result.contract.ActivityResultContracts.StartActivityForResult(),
    ) {
        isHome = AutoStart.isDefaultHome(ctx)
        msg = if (isHome) "SangaTV Plus sekarang Layar Utama." else "Belum diubah."
    }

    // Settings is reachable only while the technician menu is open.
    DisposableEffect(Unit) {
        AutoStart.maintenance = true
        kiosk.configure(apps, KioskManager.SETTINGS_PACKAGES)
        onDispose {
            AutoStart.maintenance = false
            kiosk.configure(apps)
        }
    }

    val first = remember { FocusRequester() }
    LaunchedEffect(Unit) { runCatching { first.requestFocus() } }

    val actions = buildList<Pair<String, () -> Unit>> {
        add("Muat ulang konten" to { msg = "Memuat…"; vm.refreshNow { ok -> msg = if (ok) "Konten diperbarui" else "Server tidak terjangkau" } })
        add("Cek update aplikasi" to { msg = "Memeriksa…"; vm.checkUpdate { msg = it } })
        if (kiosk.isDeviceOwner) {
            if (hardened) {
                add("Buka kunci perangkat (perawatan)" to { kiosk.relax(); hardened = false; vm.repo.logTechnicianEvent("device_relaxed"); msg = "Pembatasan dilepas. Kunci lagi setelah selesai." })
            } else {
                add("Kunci perangkat (ADB off)" to { kiosk.harden(); hardened = true; vm.repo.logTechnicianEvent("device_hardened"); msg = "Perangkat dikunci" })
            }
        }
        if (!kiosk.isDeviceOwner && !AutoStart.canDrawOverlays(ctx)) {
            add("Izinkan buka otomatis saat TV nyala" to {
                runCatching {
                    ctx.startActivity(Intent(Settings.ACTION_MANAGE_OVERLAY_PERMISSION, android.net.Uri.parse("package:${ctx.packageName}")).addFlags(Intent.FLAG_ACTIVITY_NEW_TASK))
                }.onFailure { msg = "Tidak tersedia di TV ini, gunakan perintah adb (lihat README)" }
            })
        }
        if (!kiosk.isDeviceOwner && !AutoStart.isLauncherWatchEnabled(ctx)) {
            add("Aktifkan penjaga layar utama" to {
                runCatching { ctx.startActivity(Intent(Settings.ACTION_ACCESSIBILITY_SETTINGS).addFlags(Intent.FLAG_ACTIVITY_NEW_TASK)) }
                    .onFailure { msg = "Tidak tersedia di TV ini, gunakan perintah adb (lihat README)" }
            })
        }
        // Make SangaTV Plus the HOME app again, without ADB.
        if (!isHome) {
            add("Jadikan SangaTV Plus layar utama" to {
                vm.repo.logTechnicianEvent("home_role_requested")
                AutoStart.markIntentionalExit()
                val rm = ctx.getSystemService(android.app.role.RoleManager::class.java)
                val ok = rm != null && rm.isRoleAvailable(android.app.role.RoleManager.ROLE_HOME) &&
                    runCatching { roleLauncher.launch(rm.createRequestRoleIntent(android.app.role.RoleManager.ROLE_HOME)) }.isSuccess
                if (!ok) openSettings(ctx, Intent(Settings.ACTION_HOME_SETTINGS))?.let { msg = it }
            })
        }
        // Restore the TV's own launcher without ADB: open its app page so the technician presses "Aktifkan".
        AutoStart.disabledStockLaunchers(ctx).forEach { pkg ->
            add("Pulihkan launcher bawaan (${AutoStart.appLabel(ctx, pkg)})" to {
                vm.repo.logTechnicianEvent("launcher_restore_opened", pkg)
                openSettings(ctx, Intent(Settings.ACTION_APPLICATION_DETAILS_SETTINGS, android.net.Uri.parse("package:$pkg")))
                    ?.let { msg = it }
                    ?: run { msg = "Pilih \"Aktifkan\", lalu atur aplikasi Layar Utama default bila perlu." }
            })
        }
        if (isHome) {
            add("Atur aplikasi Layar Utama default" to {
                vm.repo.logTechnicianEvent("default_home_opened")
                openSettings(ctx, Intent(Settings.ACTION_HOME_SETTINGS), Intent(Settings.ACTION_MANAGE_DEFAULT_APPS_SETTINGS))
                    ?.let { msg = it }
            })
        }
        add("Buka Pengaturan Android" to {
            vm.repo.logTechnicianEvent("settings_opened")
            openSettings(ctx, Intent(Settings.ACTION_SETTINGS))?.let { msg = it }
        })
        add((if (confirmReset) "Tekan lagi untuk daftar ulang" else "Daftar ulang TV") to {
            if (confirmReset) {
                vm.repo.logTechnicianEvent("registration_reset")
                activity?.let { kiosk.exitKiosk(it) }
                vm.resetRegistration()
            } else {
                confirmReset = true
            }
        })
        add("Keluar" to onExit)
    }

    Column(
        Modifier.width(620.px).card(RoundedCornerShape(32.px), borderWidth = 1.px).padding(48.px),
        verticalArrangement = Arrangement.spacedBy(16.px),
    ) {
        Text(stringResource(R.string.tech_title), style = body(40, 800), modifier = Modifier.padding(bottom = 12.px))
        actions.forEachIndexed { i, (label, action) ->
            Focusable(onClick = action, modifier = Modifier.fillMaxWidth().then(if (i == 0) Modifier.focusRequester(first) else Modifier)) { focused ->
                Text(
                    label,
                    style = body(24, 700, if (focused) Sanga.OnAccent else Sanga.Text),
                    modifier = Modifier.fillMaxWidth().background(if (focused) Sanga.Accent else Sanga.Surface2, RoundedCornerShape(18.px)).padding(horizontal = 28.px, vertical = 20.px),
                )
            }
        }
        Text(msg ?: "", style = body(20, 600, Sanga.Text3), modifier = Modifier.heightIn(min = 30.px))
    }
}

@Composable
private fun DeviceStatus(vm: AppViewModel, roomNumber: String?, online: Boolean) {
    val ctx = LocalContext.current
    val kiosk = vm.kiosk
    val repo = vm.repo
    val server = when {
        !online -> "Tidak terhubung"
        repo.apiHost.startsWith("https") && repo.isPinned -> "Terhubung (HTTPS, sertifikat terverifikasi)"
        repo.apiHost.startsWith("https") -> "Terhubung (HTTPS, tanpa pinning)"
        else -> "Terhubung (HTTP – mode debug)"
    }
    val rows = listOf(
        "ID perangkat" to repo.deviceUid.takeLast(12),
        "Terdaftar ke" to (roomNumber?.let { "Kamar $it" } ?: "-"),
        "Server" to server,
        "Mulai otomatis" to when {
            kiosk.isDeviceOwner -> "Ya (Device Owner, HOME terkunci)"
            AutoStart.isDefaultHome(ctx) && AutoStart.isStockLauncherActive(ctx) ->
                "Sebagian: launcher bawaan aktif & tampil dulu (nonaktifkan lewat skrip ADB)"
            AutoStart.isDefaultHome(ctx) -> "Ya (launcher default)"
            AutoStart.canDrawOverlays(ctx) && AutoStart.isLauncherWatchEnabled(ctx) -> "Ya (boot + penjaga layar utama)"
            AutoStart.canDrawOverlays(ctx) -> "Sebagian (penjaga layar utama mati)"
            else -> "Belum (izin tampil di atas aplikasi lain)"
        },
        "Mode kiosk" to when {
            kiosk.isInLockTask -> "Aktif"
            kiosk.isDeviceOwner -> "Siap (Device Owner)"
            else -> "Tidak aktif (bukan Device Owner)"
        },
        "Versi aplikasi" to "${BuildConfig.VERSION_NAME} (${BuildConfig.VERSION_CODE})",
    )
    Column(Modifier.width(620.px), verticalArrangement = Arrangement.spacedBy(20.px)) {
        Text(stringResource(R.string.tech_status), style = body(30, 700), modifier = Modifier.padding(bottom = 8.px))
        rows.forEach { (k, v) ->
            Row(
                Modifier.fillMaxWidth().background(Sanga.Card, RoundedCornerShape(20.px)).padding(horizontal = 28.px, vertical = 24.px),
                horizontalArrangement = Arrangement.SpaceBetween,
            ) {
                Text(k, style = body(22, 400, Sanga.Text3))
                Text(v, style = body(22, 700), textAlign = TextAlign.End, modifier = Modifier.padding(start = 24.px))
            }
        }
        Text(stringResource(R.string.tech_footer), style = body(19, 400, Sanga.Text3).copy(lineHeight = body(19).fontSize * 1.5f), modifier = Modifier.padding(top = 8.px))
    }
}

/** Opens the first Settings screen this TV supports. Returns an error message, or null on success. */
private fun openSettings(ctx: android.content.Context, vararg intents: Intent): String? {
    AutoStart.markIntentionalExit()
    for (intent in intents) {
        val ok = runCatching { ctx.startActivity(intent.addFlags(Intent.FLAG_ACTIVITY_NEW_TASK)) }.isSuccess
        if (ok) return null
    }
    return "Layar pengaturan ini tidak tersedia di TV ini"
}
