package com.sangatv.plus.device

import android.app.Notification
import android.app.NotificationChannel
import android.app.NotificationManager
import android.app.Service
import android.content.Intent
import android.content.IntentFilter
import android.content.pm.ServiceInfo
import android.os.Build
import android.os.IBinder
import com.sangatv.plus.R
import com.sangatv.plus.SangaApp

/**
 * Keeps the process (and the heartbeat loop) alive while guests use other apps,
 * so check-out commands still arrive while Netflix/YouTube is in the foreground.
 */
class SangaService : Service() {
    override fun onCreate() {
        super.onCreate()
        val nm = getSystemService(NotificationManager::class.java)
        nm.createNotificationChannel(NotificationChannel(CHANNEL, "SangaTV Plus", NotificationManager.IMPORTANCE_MIN))
        val n = Notification.Builder(this, CHANNEL)
            .setSmallIcon(R.drawable.banner)
            .setContentTitle(getString(R.string.app_name))
            .setOngoing(true)
            .build()
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.UPSIDE_DOWN_CAKE) {
            startForeground(ID, n, ServiceInfo.FOREGROUND_SERVICE_TYPE_SPECIAL_USE)
        } else {
            startForeground(ID, n)
        }
        (application as SangaApp).repository.start()
        SleepTimer.restore(this) // in case the app died between a sleep-timer standby and the next wake
        if (AutoStart.isEarlyBoot()) {
            TvDefaults.freshSession = true
            TvDefaults.applyVolume(this, (application as SangaApp).repository.bootstrap.value?.config?.startVolume)
        }
        bootGuard()
        overlay = AnnouncementOverlay(this, (application as SangaApp).repository).also { it.start() }
        registerReceiver(wakeReceiver, IntentFilter().apply {
            addAction(Intent.ACTION_SCREEN_ON)
            addAction(Intent.ACTION_SCREEN_OFF)
            addAction(Intent.ACTION_DREAMING_STOPPED) // Android TV screensaver / ambient mode ended
        })
    }

    /**
     * TV woken from standby (remote power button) or screensaver ended: Android would resume
     * whatever was last on screen (stock launcher, Live TV, Netflix …). Always land on our Home.
     */
    private val wakeReceiver = object : android.content.BroadcastReceiver() {
        override fun onReceive(context: android.content.Context, intent: Intent) {
            // Tell the CMS right away so monitoring shows Menyala / Standby without waiting.
            val repo = (application as SangaApp).repository
            repo.beatNow()
            if (intent.action == Intent.ACTION_SCREEN_OFF) {
                AdultLock.relock() // next guest / next session must enter the PIN again
                SleepTimer.cancel()
                return
            }
            if (intent.action == Intent.ACTION_SCREEN_ON) {
                SleepTimer.restore(this@SangaService)
                if (ServiceState.isReleased) return // Mode lepas: leave the TV alone
                TvDefaults.freshSession = true
                TvDefaults.applyVolume(this@SangaService, repo.bootstrap.value?.config?.startVolume)
            }
            // Small delay: some TVs (MediaTek) push their own app right after the screen turns on.
            handler.postDelayed({ AutoStart.openMain(this@SangaService, debounceMs = 0, goHome = true) }, 700)
            handler.postDelayed({
                if (!AutoStart.isAppForeground) AutoStart.openMain(this@SangaService, debounceMs = 0, goHome = true)
            }, 3500)
        }
    }

    private val handler = android.os.Handler(android.os.Looper.getMainLooper())

    /**
     * During early boot some TVs (MediaTek Coocaa) push their Live-TV app over HOME before our
     * activity even exists. Poll and bring SangaTV Plus back until the boot window ends.
     */
    private fun bootGuard() {
        handler.post(object : Runnable {
            override fun run() {
                if (!AutoStart.isEarlyBoot()) return
                if (!AutoStart.isAppForeground && AutoStart.shouldReclaimAfterStop()) {
                    AutoStart.openMain(this@SangaService, debounceMs = 2500)
                }
                handler.postDelayed(this, 1500)
            }
        })
    }

    /** Announcements on top of YouTube / Netflix / HDMI while SangaTV Plus is not on screen. */
    private var overlay: AnnouncementOverlay? = null

    override fun onDestroy() {
        overlay?.stop()
        runCatching { unregisterReceiver(wakeReceiver) }
        handler.removeCallbacksAndMessages(null)
        super.onDestroy()
    }

    override fun onStartCommand(intent: Intent?, flags: Int, startId: Int): Int = START_STICKY

    override fun onBind(intent: Intent?): IBinder? = null

    private companion object {
        const val CHANNEL = "sangatv_service"
        const val ID = 1
    }
}
