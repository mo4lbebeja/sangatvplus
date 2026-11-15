package com.sangatv.plus.device

import android.accessibilityservice.AccessibilityService
import android.view.accessibility.AccessibilityEvent

/**
 * Fallback when SangaTV Plus cannot be the default HOME (not Device Owner, and always on Google TV,
 * which ignores the HOME role): whenever the TV's stock launcher comes to the front, reopen SangaTV Plus.
 * Only window-state events are read; no screen content is accessed.
 */
class LauncherWatchService : AccessibilityService() {
    private val launchers = AutoStart.STOCK_LAUNCHERS.toSet() + "com.android.launcher3"

    override fun onServiceConnected() {
        AutoStart.openMain(this)
    }

    override fun onAccessibilityEvent(event: AccessibilityEvent?) {
        if (event?.eventType != AccessibilityEvent.TYPE_WINDOW_STATE_CHANGED) return
        val pkg = event.packageName?.toString() ?: return
        if (pkg in launchers || pkg.contains("launcher", ignoreCase = true)) {
            // Short debounce: every HOME press must come back (Google TV always opens its own launcher first).
            AutoStart.openMain(this, debounceMs = 700, goHome = true)
        }
    }

    override fun onInterrupt() = Unit
}
