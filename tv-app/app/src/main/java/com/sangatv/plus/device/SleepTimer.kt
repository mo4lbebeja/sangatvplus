package com.sangatv.plus.device

import android.app.admin.DevicePolicyManager
import android.content.Context
import android.provider.Settings
import android.util.Log
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow

/**
 * Guest sleep timer ("matikan TV dalam 30 menit").
 *
 * Putting an Android TV into standby needs a system permission, so at expiry we use the
 * strongest method available:
 *  1. Device Owner → DevicePolicyManager.lockNow() (screen off at once).
 *  2. WRITE_SECURE_SETTINGS (granted once by siapkan-tv.sh) → shorten Settings.Secure
 *     "sleep_timeout" so the TV goes to standby a few seconds later. The original values are
 *     saved first and restored on the next wake / app start / boot (see [restore]).
 *  3. Otherwise the UI stops playback and dims the screen; the TV's own timeout takes over.
 */
object SleepTimer {
    val OPTIONS_MIN = listOf(0, 15, 30, 60, 90, 120)

    private val _endsAt = MutableStateFlow(0L)
    /** Epoch ms when the TV should sleep; 0 = off. */
    val endsAt: StateFlow<Long> = _endsAt.asStateFlow()

    private const val PREFS = "sleep_timer"
    private const val SAVED_SLEEP = "saved_sleep_timeout"
    private const val SAVED_SCREEN = "saved_screen_off_timeout"
    private const val SECURE_SLEEP_TIMEOUT = "sleep_timeout"

    val minutesLeft: Int get() = if (_endsAt.value == 0L) 0 else ((_endsAt.value - System.currentTimeMillis() + 59_999) / 60_000).toInt().coerceAtLeast(0)

    /** Next option after the current one (OK on the menu item cycles through them). */
    fun cycle(): Int {
        val cur = minutesLeft
        val next = OPTIONS_MIN.firstOrNull { it > cur } ?: 0
        set(next)
        return next
    }

    fun set(minutes: Int) {
        _endsAt.value = if (minutes <= 0) 0L else System.currentTimeMillis() + minutes * 60_000L
    }

    fun cancel() = set(0)

    fun canForceStandby(context: Context): Boolean =
        isDeviceOwner(context) || context.checkSelfPermission(android.Manifest.permission.WRITE_SECURE_SETTINGS) == android.content.pm.PackageManager.PERMISSION_GRANTED

    /** Called when the timer expires. @return true if the TV will go to standby by itself. */
    fun sleepNow(context: Context): Boolean {
        _endsAt.value = 0L
        if (isDeviceOwner(context)) {
            runCatching { context.getSystemService(DevicePolicyManager::class.java).lockNow() }
                .onSuccess { return true }
        }
        if (context.checkSelfPermission(android.Manifest.permission.WRITE_SECURE_SETTINGS) != android.content.pm.PackageManager.PERMISSION_GRANTED) return false
        val cr = context.contentResolver
        val prefs = context.getSharedPreferences(PREFS, Context.MODE_PRIVATE)
        return runCatching {
            if (!prefs.contains(SAVED_SLEEP)) {
                prefs.edit()
                    .putInt(SAVED_SLEEP, Settings.Secure.getInt(cr, SECURE_SLEEP_TIMEOUT, -1))
                    .putInt(SAVED_SCREEN, Settings.System.getInt(cr, Settings.System.SCREEN_OFF_TIMEOUT, -1))
                    .commit()
            }
            Settings.Secure.putInt(cr, SECURE_SLEEP_TIMEOUT, 5_000)
            if (Settings.System.canWrite(context)) Settings.System.putInt(cr, Settings.System.SCREEN_OFF_TIMEOUT, 5_000)
            true
        }.onFailure { Log.w("SleepTimer", "cannot shorten sleep timeout", it) }.getOrDefault(false)
    }

    /** Put back the TV's own timeouts after a sleep-timer standby. Safe to call any time. */
    fun restore(context: Context) {
        val prefs = context.getSharedPreferences(PREFS, Context.MODE_PRIVATE)
        if (!prefs.contains(SAVED_SLEEP)) return
        val cr = context.contentResolver
        runCatching {
            prefs.getInt(SAVED_SLEEP, -1).takeIf { it > 0 }?.let { Settings.Secure.putInt(cr, SECURE_SLEEP_TIMEOUT, it) }
            prefs.getInt(SAVED_SCREEN, -1).takeIf { it > 0 && Settings.System.canWrite(context) }?.let { Settings.System.putInt(cr, Settings.System.SCREEN_OFF_TIMEOUT, it) }
            prefs.edit().clear().commit()
        }.onFailure { Log.w("SleepTimer", "restore failed", it) }
    }

    private fun isDeviceOwner(context: Context) =
        context.getSystemService(DevicePolicyManager::class.java)?.isDeviceOwnerApp(context.packageName) == true
}
