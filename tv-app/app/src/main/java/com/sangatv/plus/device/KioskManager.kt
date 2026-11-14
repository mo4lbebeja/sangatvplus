package com.sangatv.plus.device

import android.app.Activity
import android.app.ActivityManager
import android.app.admin.DevicePolicyManager
import android.content.Context
import android.content.Intent
import android.content.IntentFilter
import android.os.UserManager
import android.provider.Settings
import android.util.Log
import com.sangatv.plus.MainActivity
import java.util.concurrent.Executors
import kotlin.coroutines.resume
import kotlinx.coroutines.suspendCancellableCoroutine

/**
 * Everything that needs Device Owner: HOME lock, Lock Task (kiosk), device hardening,
 * clearing guest app data and reboot. Every method is a safe no-op when the app is not
 * Device Owner (e.g. on a development emulator).
 */
class KioskManager(private val context: Context) {
    private val dpm = context.getSystemService(DevicePolicyManager::class.java)
    private val am = context.getSystemService(ActivityManager::class.java)
    private val admin = SangaDeviceAdminReceiver.component(context)

    val isDeviceOwner: Boolean get() = dpm.isDeviceOwnerApp(context.packageName)

    val isInLockTask: Boolean get() = am.lockTaskModeState != ActivityManager.LOCK_TASK_MODE_NONE

    /** Packages allowed to run while locked (own app + whitelist + optional extras like Settings). */
    fun configure(allowedPackages: Collection<String>, extra: Collection<String> = emptyList()) {
        if (!isDeviceOwner) return
        val pkgs = (listOf(context.packageName) + allowedPackages + extra).distinct().toTypedArray()
        dpm.setLockTaskPackages(admin, pkgs)
        dpm.setLockTaskFeatures(
            admin,
            DevicePolicyManager.LOCK_TASK_FEATURE_HOME or DevicePolicyManager.LOCK_TASK_FEATURE_SYSTEM_INFO,
        )
        // HOME (boot and remote HOME key) always resolves to SangaTV Plus.
        val home = IntentFilter(Intent.ACTION_MAIN).apply {
            addCategory(Intent.CATEGORY_HOME)
            addCategory(Intent.CATEGORY_DEFAULT)
        }
        dpm.addPersistentPreferredActivity(admin, home, android.content.ComponentName(context, MainActivity::class.java))
    }

    fun enterKiosk(activity: Activity) {
        if (!isDeviceOwner || isInLockTask) return
        runCatching { activity.startLockTask() }.onFailure { Log.w(TAG, "startLockTask failed", it) }
    }

    fun exitKiosk(activity: Activity) {
        if (isInLockTask) runCatching { activity.stopLockTask() }
    }

    /** After provisioning: ADB/developer options off and guest-proof restrictions on. */
    fun harden() {
        if (!isDeviceOwner) return
        RESTRICTIONS.forEach { runCatching { dpm.addUserRestriction(admin, it) } }
        runCatching { dpm.setGlobalSetting(admin, Settings.Global.ADB_ENABLED, "0") }
    }

    /** Technician maintenance: lift restrictions (ADB can then be re-enabled in Settings). */
    fun relax() {
        if (!isDeviceOwner) return
        RESTRICTIONS.forEach { runCatching { dpm.clearUserRestriction(admin, it) } }
    }

    val isHardened: Boolean
        get() = isDeviceOwner && dpm.getUserRestrictions(admin).getBoolean(UserManager.DISALLOW_DEBUGGING_FEATURES)

    /** Wipes login/data of guest apps (YouTube, Netflix, …). Returns per-package success. */
    suspend fun clearAppData(packages: List<String>): Map<String, Boolean> {
        if (!isDeviceOwner) return packages.associateWith { false }
        val exec = Executors.newSingleThreadExecutor()
        return try {
            packages.filter { it != context.packageName }.associateWith { pkg ->
                suspendCancellableCoroutine { cont ->
                    try {
                        dpm.clearApplicationUserData(admin, pkg, exec) { _, ok -> if (cont.isActive) cont.resume(ok) }
                    } catch (e: Exception) {
                        Log.w(TAG, "clear $pkg failed", e)
                        if (cont.isActive) cont.resume(false)
                    }
                }
            }
        } finally {
            exec.shutdown()
        }
    }

    /** Device Owner only: hide/unhide the TV's stock launchers so SangaTV Plus is the only HOME. */
    fun setStockLaunchersHidden(hidden: Boolean): Map<String, Boolean> {
        if (!isDeviceOwner) return emptyMap()
        return AutoStart.STOCK_LAUNCHERS
            .filter { pkg -> runCatching { context.packageManager.getApplicationInfo(pkg, android.content.pm.PackageManager.MATCH_UNINSTALLED_PACKAGES) }.isSuccess }
            .associateWith { pkg -> runCatching { dpm.setApplicationHidden(admin, pkg, hidden) }.getOrDefault(false) }
    }

    fun reboot() {
        if (isDeviceOwner) runCatching { dpm.reboot(admin) }
    }

    companion object {
        private const val TAG = "KioskManager"
        val RESTRICTIONS = listOf(
            UserManager.DISALLOW_DEBUGGING_FEATURES,
            UserManager.DISALLOW_INSTALL_UNKNOWN_SOURCES,
            UserManager.DISALLOW_FACTORY_RESET,
            UserManager.DISALLOW_SAFE_BOOT,
            UserManager.DISALLOW_ADD_USER,
            UserManager.DISALLOW_MODIFY_ACCOUNTS,
            UserManager.DISALLOW_USB_FILE_TRANSFER,
            UserManager.DISALLOW_MOUNT_PHYSICAL_MEDIA,
        )
        val SETTINGS_PACKAGES = listOf("com.android.tv.settings", "com.android.settings", "com.google.android.tv.settings")
    }
}
