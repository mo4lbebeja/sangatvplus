package com.sangatv.plus.device

import android.app.Notification
import android.app.NotificationChannel
import android.app.NotificationManager
import android.app.Service
import android.content.Intent
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
        bootGuard()
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

    override fun onDestroy() {
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
