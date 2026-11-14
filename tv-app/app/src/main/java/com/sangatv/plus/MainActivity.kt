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
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.lifecycle.compose.collectAsStateWithLifecycle
import androidx.compose.ui.Modifier
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

    Box(Modifier.fillMaxSize().background(Sanga.Deepest)) {
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
                InfoScreen(bootstrap, facilities?.facilities.orEmpty(), s.section, vm::goHome)
            }
            Screen.Apps -> {
                BackHandler { vm.goHome() }
                AppsScreen(bootstrap?.apps.orEmpty())
            }
            Screen.Technician -> {
                BackHandler { vm.goHome() }
                TechnicianScreen(vm, bootstrap?.room?.number, online, vm::goHome)
            }
        }
    }
}
