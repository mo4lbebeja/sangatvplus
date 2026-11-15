package com.sangatv.plus

import android.content.Context
import android.content.Intent
import android.content.res.Configuration
import android.os.Bundle
import androidx.activity.ComponentActivity
import androidx.activity.compose.BackHandler
import androidx.activity.compose.setContent
import androidx.activity.viewModels
import androidx.lifecycle.lifecycleScope
import kotlinx.coroutines.launch
import java.util.Locale
import androidx.compose.foundation.background
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.padding
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.lifecycle.compose.collectAsStateWithLifecycle
import androidx.compose.ui.Modifier
import androidx.compose.foundation.focusable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.mutableIntStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.focus.FocusRequester
import androidx.compose.ui.focus.focusRequester
import androidx.compose.ui.input.key.Key
import androidx.compose.ui.input.key.KeyEventType
import androidx.compose.ui.input.key.key
import androidx.compose.ui.input.key.onKeyEvent
import androidx.compose.ui.input.key.onPreviewKeyEvent
import androidx.compose.ui.input.key.type
import androidx.compose.ui.platform.LocalContext
import kotlinx.coroutines.delay
import com.sangatv.plus.device.SleepTimer
import com.sangatv.plus.ui.components.AnnouncementModal
import com.sangatv.plus.ui.components.AnnouncementTicker
import com.sangatv.plus.ui.components.SleepScreen
import com.sangatv.plus.ui.components.SleepWarning
import com.sangatv.plus.device.AutoStart
import com.sangatv.plus.ui.AppViewModel
import com.sangatv.plus.ui.Screen
import com.sangatv.plus.ui.screens.AppsScreen
import com.sangatv.plus.ui.screens.ChannelScreen
import com.sangatv.plus.ui.screens.HomeScreen
import com.sangatv.plus.ui.screens.InfoScreen
import com.sangatv.plus.ui.screens.ProvisionScreen
import com.sangatv.plus.ui.screens.TechnicianScreen
import com.sangatv.plus.ui.theme.Sanga
import com.sangatv.plus.ui.theme.px
import com.sangatv.plus.ui.theme.SangaTheme

/**
 * HOME launcher. Boot → this activity (persistent preferred HOME as Device Owner)
 * → Home screen. The remote's HOME key re-delivers an intent here → back to Home.
 */
class MainActivity : ComponentActivity() {
    private val vm: AppViewModel by viewModels()

    /** UI language: Indonesian by default (doc §7), English when the in-house guest is set to "en" in the CMS. */
    override fun attachBaseContext(newBase: Context) {
        val lang = newBase.getSharedPreferences("viewer", MODE_PRIVATE).getString(UI_LANG, "id") ?: "id"
        val config = Configuration(newBase.resources.configuration).apply { setLocale(Locale.forLanguageTag(lang)) }
        super.attachBaseContext(newBase.createConfigurationContext(config))
    }

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        AutoStart.startService(this)
        SleepTimer.restore(this) // TV woke up after a sleep-timer standby
        setContent { SangaTheme { Root(vm) } }

        // Follow the guest's language; recreate only when it actually changes.
        lifecycleScope.launch {
            vm.bootstrap.collect { b ->
                val want = if (b?.guest?.language == "en") "en" else "id"
                val prefs = getSharedPreferences("viewer", MODE_PRIVATE)
                if (prefs.getString(UI_LANG, "id") != want) {
                    prefs.edit().putString(UI_LANG, want).apply()
                    recreate()
                }
            }
        }
    }

    override fun onResume() {
        super.onResume()
        val kiosk = (application as SangaApp).kiosk
        kiosk.configure(vm.bootstrap.value?.apps.orEmpty().map { it.packageName })
        if (vm.screen.value != Screen.Technician) kiosk.enterKiosk(this)
        (application as SangaApp).repository.start()
    }

    private companion object {
        const val UI_LANG = "ui_lang"
    }

    private var isTopResumed = false

    /**
     * Boot guard. onTopResumedActivityChanged(false) fires the moment another app covers us
     * (onStop can lag 15 s on slow TVs). Re-check after 1 s so brief overlays (IME, dialogs)
     * don't trigger it.
     */
    override fun onTopResumedActivityChanged(isTopResumedActivity: Boolean) {
        super.onTopResumedActivityChanged(isTopResumedActivity)
        isTopResumed = isTopResumedActivity
        AutoStart.isAppForeground = isTopResumedActivity
        if (!isTopResumedActivity && AutoStart.shouldReclaimAfterStop()) {
            val app = applicationContext
            android.os.Handler(mainLooper).postDelayed({
                if (!isTopResumed && AutoStart.shouldReclaimAfterStop()) AutoStart.openMain(app, debounceMs = 0)
            }, 1000)
        }
    }

    override fun onStop() {
        super.onStop()
        isTopResumed = false
        AutoStart.isAppForeground = false
        if (AutoStart.shouldReclaimAfterStop()) {
            val app = applicationContext
            android.os.Handler(mainLooper).postDelayed({ AutoStart.openMain(app, debounceMs = 0) }, 800)
        }
    }

    override fun onNewIntent(intent: Intent) {
        super.onNewIntent(intent)
        val toHome = intent.hasCategory(Intent.CATEGORY_HOME) || intent.getBooleanExtra(AutoStart.EXTRA_GO_HOME, false)
        if (toHome && vm.screen.value != Screen.Technician) vm.goHome()
    }
}

@Composable
private fun Root(vm: AppViewModel) {
    val provisioned by vm.provisioned.collectAsStateWithLifecycle()
    val screen by vm.screen.collectAsStateWithLifecycle()
    val bootstrap by vm.bootstrap.collectAsStateWithLifecycle()
    val channels by vm.channels.collectAsStateWithLifecycle()
    val facilities by vm.facilities.collectAsStateWithLifecycle()
    val online by vm.online.collectAsStateWithLifecycle()
    val provisionState by vm.provisionState.collectAsStateWithLifecycle()
    val announcements by vm.repo.announcements.collectAsStateWithLifecycle()
    val dismissed by vm.dismissed.collectAsStateWithLifecycle()
    val forceChannel by vm.repo.forceChannel.collectAsStateWithLifecycle()
    val sleepEnds by SleepTimer.endsAt.collectAsStateWithLifecycle()
    val context = LocalContext.current

    // Announcement "pindahkan ke channel": open the TV screen; ChannelScreen tunes and consumes it.
    LaunchedEffect(forceChannel, provisioned) {
        if (forceChannel != null && provisioned && screen !is Screen.Tv && screen != Screen.Technician) vm.open(Screen.Tv)
    }

    // Sleep timer: warning during the last minute, then standby.
    var sleepSecs by remember { mutableIntStateOf(-1) }
    LaunchedEffect(sleepEnds) {
        sleepSecs = -1
        if (sleepEnds == 0L) return@LaunchedEffect
        while (true) {
            val left = ((sleepEnds - System.currentTimeMillis()) / 1000).toInt()
            if (left <= 0) {
                sleepSecs = -1
                val standby = SleepTimer.sleepNow(context)
                vm.open(Screen.Sleep(standby))
                break
            }
            sleepSecs = if (left <= 60) left else -1
            delay(1000)
        }
    }

    val live = announcements.filter { a -> a.endsAt == null || runCatching { java.time.OffsetDateTime.parse(a.endsAt).toInstant().isAfter(java.time.Instant.now()) }.getOrDefault(true) }
    val modal = live.firstOrNull { it.style == "fullscreen" && (it.isEmergency || it.version !in dismissed) }
    val tickers = live.filter { it != modal && (it.style != "fullscreen" || it.version in dismissed) }

    Box(
        Modifier.fillMaxSize().background(Sanga.Deepest).onPreviewKeyEvent { e ->
            if (e.type != KeyEventType.KeyDown) return@onPreviewKeyEvent false
            val volume = e.key == Key.VolumeUp || e.key == Key.VolumeDown || e.key == Key.VolumeMute
            when {
                sleepSecs >= 0 && !volume -> { SleepTimer.cancel(); true }
                modal != null && !volume -> {
                    if (!modal.isEmergency && (e.key == Key.DirectionCenter || e.key == Key.Enter || e.key == Key.NumPadEnter || e.key == Key.Back)) {
                        vm.dismissAnnouncement(modal.version)
                    }
                    true
                }
                else -> false
            }
        },
    ) {
        if (!provisioned) {
            ProvisionScreen(provisionState, vm.repo.deviceUid, vm.repo.apiHost, vm::provision)
            return@Box
        }
        when (val s = screen) {
            Screen.Home -> {
                BackHandler { /* launcher: BACK on Home does nothing */ }
                HomeScreen(bootstrap, online, vm::open)
            }
            Screen.Tv -> ChannelScreen(
                data = channels,
                zone = bootstrap?.hotel?.timezone ?: "Asia/Jakarta",
                initial = vm::initialChannel,
                onTuned = { vm.lastChannelId = it.id },
                onExit = vm::goHome,
            )
            is Screen.Info -> {
                BackHandler { vm.goHome() }
                InfoScreen(bootstrap, facilities?.facilities.orEmpty(), facilities?.gallery.orEmpty(), s.section, vm::goHome)
            }
            Screen.Apps -> {
                BackHandler { vm.goHome() }
                AppsScreen(bootstrap?.apps.orEmpty(), bootstrap?.config?.showHdmiInputs ?: true)
            }
            Screen.Technician -> {
                BackHandler { vm.goHome() }
                TechnicianScreen(vm, bootstrap?.room?.number, online, vm::goHome)
            }
            is Screen.Sleep -> {
                BackHandler { vm.goHome() }
                val focus = remember { FocusRequester() }
                LaunchedEffect(Unit) { runCatching { focus.requestFocus() } }
                Box(
                    Modifier.fillMaxSize().focusRequester(focus).focusable()
                        .onKeyEvent { e -> if (e.type == KeyEventType.KeyDown) { vm.goHome(); true } else false },
                ) { SleepScreen(s.standby) }
            }
        }

        if (screen != Screen.Technician && screen !is Screen.Sleep) {
            // Home: sit on top of the bottom menu bar (150 px) instead of covering it.
            AnnouncementTicker(tickers, Modifier.align(Alignment.BottomCenter).padding(bottom = if (screen == Screen.Home) 150.px else 0.px))
            modal?.let { AnnouncementModal(it) }
            if (sleepSecs >= 0) SleepWarning(sleepSecs, Modifier.align(Alignment.Center))
        }
    }
}
