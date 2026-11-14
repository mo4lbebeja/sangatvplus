package com.sangatv.plus.device

import android.app.admin.DeviceAdminReceiver
import android.content.ComponentName
import android.content.Context

/**
 * Device admin component. Becomes Device Owner during provisioning:
 *   adb shell dpm set-device-owner com.sangatv.plus/.device.SangaDeviceAdminReceiver
 */
class SangaDeviceAdminReceiver : DeviceAdminReceiver() {
    companion object {
        fun component(context: Context) = ComponentName(context, SangaDeviceAdminReceiver::class.java)
    }
}
