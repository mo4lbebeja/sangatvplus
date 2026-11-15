package com.sangatv.plus.device

import android.content.Context
import android.content.Intent
import android.os.Build
import android.provider.Settings
import android.text.TextUtils
import com.sangatv.plus.MainActivity

/**
 * TV power-on → SangaTV Plus, even when the app is not Device Owner.
 * (Same approach as the HotelTV app, proven on Sharp/TCL.)
 *
 *  1. Device Owner: HOME is locked to MainActivity → nothing else needed.
 *  2. Otherwise: BootReceiver starts [SangaService] and opens MainActivity
 *     (needs "Display over other apps" on Android 10+), and
 *     [LauncherWatchService] pulls the guest back if the stock launcher appears.
 */
object AutoStart {
    /** True while the technician menu is open: don't pull the TV back from Settings. */
    @Volatile var maintenance = false

    @Volatile private var lastOpen = 0L

    /** True while MainActivity is the top resumed activity. */
    @Volatile var isAppForeground = false

    fun isEarlyBoot(): Boolean = android.os.SystemClock.elapsedRealtime() < BOOT_GUARD_MS

    /** Set whenever WE intentionally hand the screen to another app (guest opens YouTube, technician opens Settings). */
    @Volatile var lastIntentionalExit = 0L

    fun markIntentionalExit() {
        lastIntentionalExit = System.currentTimeMillis()
    }

    /**
     * Boot guard: during the first minutes after power-on some TVs (e.g. MediaTek-based Coocaa)
     * bring their own Live-TV app to the front right after HOME. If we lose the screen in that
     * window and it wasn't the guest's or technician's doing, come straight back.
     */
    fun shouldReclaimAfterStop(): Boolean =
        android.os.SystemClock.elapsedRealtime() < BOOT_GUARD_MS &&
            !maintenance &&
            System.currentTimeMillis() - lastIntentionalExit > 5_000

    private const val BOOT_GUARD_MS = 4 * 60_000L

    const val EXTRA_GO_HOME = "sangatv.go_home"

    fun openMain(context: Context, debounceMs: Long = 3000, goHome: Boolean = false) {
        val now = System.currentTimeMillis()
        // Mode lepas: never pull SangaTV Plus to the front again.
        if (maintenance || ServiceState.isReleased || now - lastOpen < debounceMs) return
        lastOpen = now
        runCatching {
            context.startActivity(
                Intent(context, MainActivity::class.java)
                    .putExtra(EXTRA_GO_HOME, goHome)
                    .addFlags(Intent.FLAG_ACTIVITY_NEW_TASK or Intent.FLAG_ACTIVITY_REORDER_TO_FRONT or Intent.FLAG_ACTIVITY_SINGLE_TOP),
            )
        }
    }

    fun startService(context: Context) {
        runCatching {
            val i = Intent(context, SangaService::class.java)
            if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) context.startForegroundService(i) else context.startService(i)
        }
    }

    fun canDrawOverlays(context: Context) = Settings.canDrawOverlays(context)

    fun isLauncherWatchEnabled(context: Context): Boolean {
        val enabled = Settings.Secure.getString(context.contentResolver, Settings.Secure.ENABLED_ACCESSIBILITY_SERVICES) ?: return false
        val me = "${context.packageName}/${LauncherWatchService::class.java.name}"
        return TextUtils.SimpleStringSplitter(':').apply { setString(enabled) }.any { it.equals(me, true) }
    }

    /** Stock launchers seen on Google TV / Android TV / TCL / Skyworth-Coocaa. */
    val STOCK_LAUNCHERS = listOf(
        "com.google.android.tvlauncher",
        "com.google.android.apps.tv.launcherx",
        "com.google.android.leanbacklauncher",
        "com.android.tvlauncher",
        "com.tcl.launcher",
        "com.tcl.tvlauncher",
        "com.skyworth.launcher",
        "com.coocaa.launcher",
    )

    fun isDefaultHome(context: Context): Boolean {
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.Q) {
            val rm = context.getSystemService(android.app.role.RoleManager::class.java)
            if (rm != null && rm.isRoleAvailable(android.app.role.RoleManager.ROLE_HOME)) {
                return rm.isRoleHeld(android.app.role.RoleManager.ROLE_HOME)
            }
        }
        val home = Intent(Intent.ACTION_MAIN).addCategory(Intent.CATEGORY_HOME)
        return context.packageManager.resolveActivity(home, 0)?.activityInfo?.packageName == context.packageName
    }

    /** True if any stock launcher is installed, enabled and not hidden (it would win the boot race). */
    fun isStockLauncherActive(context: Context): Boolean = STOCK_LAUNCHERS.any { pkg ->
        runCatching { context.packageManager.getApplicationInfo(pkg, 0).enabled }.getOrDefault(false)
    }

    /** Stock launchers that are installed and enabled (they win the boot race against us). */
    fun activeStockLaunchers(context: Context): List<String> = STOCK_LAUNCHERS.filter { pkg ->
        runCatching { context.packageManager.getApplicationInfo(pkg, 0).enabled }.getOrDefault(false)
    }

    /** Stock launchers that are installed but disabled (e.g. by siapkan-tv.sh). */
    fun disabledStockLaunchers(context: Context): List<String> = STOCK_LAUNCHERS.filter { pkg ->
        runCatching {
            !context.packageManager.getApplicationInfo(pkg, android.content.pm.PackageManager.MATCH_DISABLED_COMPONENTS).enabled
        }.getOrDefault(false)
    }

    fun appLabel(context: Context, pkg: String): String = runCatching {
        val pm = context.packageManager
        pm.getApplicationLabel(pm.getApplicationInfo(pkg, android.content.pm.PackageManager.MATCH_DISABLED_COMPONENTS)).toString()
    }.getOrDefault(pkg)

    fun canInstallUpdates(context: Context): Boolean = context.packageManager.canRequestPackageInstalls()
}
