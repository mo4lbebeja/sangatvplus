package com.sangatv.plus.data.local

import android.content.Context
import java.io.File

/** Last good API responses, so the launcher still renders when the server is unreachable. */
class CacheStore(context: Context) {
    private val dir = File(context.filesDir, "api-cache").apply { mkdirs() }

    fun read(name: String): String? = File(dir, "$name.json").takeIf { it.exists() }?.readText()

    fun write(name: String, json: String) {
        val tmp = File(dir, "$name.json.tmp")
        tmp.writeText(json)
        tmp.renameTo(File(dir, "$name.json"))
    }

    fun clear() {
        dir.listFiles()?.forEach { it.delete() }
    }
}
