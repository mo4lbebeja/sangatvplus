package com.sangatv.plus.device

import android.content.BroadcastReceiver
import android.content.Context
import android.content.Intent
import android.os.Handler
import android.os.Looper

/** Power-on / app update → start the service, then bring SangaTV Plus to the front. */
class BootReceiver : BroadcastReceiver() {
    override fun onReceive(context: Context, intent: Intent) {
        when (intent.action) {
            Intent.ACTION_BOOT_COMPLETED, "android.intent.action.QUICKBOOT_POWERON", Intent.ACTION_MY_PACKAGE_REPLACED -> {
                AutoStart.maintenance = false
                AutoStart.startService(context)
                val app = context.applicationContext
                Handler(Looper.getMainLooper()).postDelayed({ AutoStart.openMain(app, debounceMs = 0) }, 1500)
            }
        }
    }
}
