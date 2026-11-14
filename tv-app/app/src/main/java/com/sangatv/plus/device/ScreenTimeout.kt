package com.sangatv.plus.device

import android.content.Context
import android.provider.Settings
import android.util.Log

/**
 * Applies the CMS "screensaver after N minutes" setting. On Android TV the screensaver
 * (Ambient/Backdrop) starts when SCREEN_OFF_TIMEOUT elapses. Needs the WRITE_SETTINGS
 * app-op, granted once by siapkan-tv.sh (Device Owner TVs can also grant it).
 */
object ScreenTimeout {
    fun canWrite(context: Context): Boolean = Settings.System.canWrite(context)

    fun currentMinutes(context: Context): Int =
        runCatching { Settings.System.getInt(context.contentResolver, Settings.System.SCREEN_OFF_TIMEOUT) / 60_000 }.getOrDefault(-1)

    fun apply(context: Context, minutes: Int) {
        if (minutes <= 0 || !canWrite(context)) return
        val ms = minutes * 60_000
        val cr = context.contentResolver
        if (runCatching { Settings.System.getInt(cr, Settings.System.SCREEN_OFF_TIMEOUT) }.getOrNull() == ms) return
        runCatching { Settings.System.putInt(cr, Settings.System.SCREEN_OFF_TIMEOUT, ms) }
            .onFailure { Log.w("ScreenTimeout", "cannot set screen timeout", it) }
    }
}
