package com.sangatv.plus.ui.components

import android.graphics.Bitmap
import androidx.compose.foundation.Image
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.foundation.interaction.MutableInteractionSource
import androidx.compose.foundation.interaction.collectIsFocusedAsState
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.size
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.scale
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.ColorFilter
import androidx.compose.ui.graphics.ImageBitmap
import androidx.compose.ui.graphics.Shape
import androidx.compose.ui.graphics.asImageBitmap
import androidx.compose.ui.graphics.vector.ImageVector
import androidx.compose.ui.unit.Dp
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.runtime.State
import androidx.compose.ui.Alignment
import androidx.tv.material3.Text
import com.google.zxing.BarcodeFormat
import com.google.zxing.EncodeHintType
import com.google.zxing.qrcode.QRCodeWriter
import com.sangatv.plus.ui.theme.Sanga
import com.sangatv.plus.ui.theme.body
import com.sangatv.plus.ui.theme.px
import kotlinx.coroutines.delay
import java.time.ZoneId
import java.time.ZonedDateTime
import java.time.format.DateTimeFormatter
import java.util.Locale

@Composable
fun SIcon(icon: ImageVector, tint: Color, size: Dp, modifier: Modifier = Modifier) {
    Image(icon, contentDescription = null, colorFilter = ColorFilter.tint(tint), modifier = modifier.size(size))
}

/**
 * D-pad focusable + clickable element. Focus is always visible (doc §7: scale ≈1.06 + accent).
 * The caller draws its own look from the provided `focused` flag.
 */
@Composable
fun Focusable(
    onClick: () -> Unit,
    modifier: Modifier = Modifier,
    scaleOnFocus: Boolean = true,
    interaction: MutableInteractionSource = remember { MutableInteractionSource() },
    content: @Composable (focused: Boolean) -> Unit,
) {
    val focused by interaction.collectIsFocusedAsState()
    Box(
        modifier
            .scale(if (focused && scaleOnFocus) 1.06f else 1f)
            .clickable(interactionSource = interaction, indication = null, onClick = onClick),
    ) { content(focused) }
}

/** Ticking clock in the hotel's timezone. */
@Composable
fun rememberNow(zone: String): State<ZonedDateTime> {
    val zoneId = remember(zone) { runCatching { ZoneId.of(zone) }.getOrDefault(ZoneId.of("Asia/Jakarta")) }
    val now = remember(zoneId) { mutableStateOf(ZonedDateTime.now(zoneId)) }
    LaunchedEffect(zoneId) {
        while (true) {
            now.value = ZonedDateTime.now(zoneId)
            delay(1000L * (60 - now.value.second).coerceAtLeast(1))
        }
    }
    return now
}

private val ID = Locale.forLanguageTag("id-ID")
val TimeFmt: DateTimeFormatter = DateTimeFormatter.ofPattern("HH.mm", ID)
val DateFmt: DateTimeFormatter = DateTimeFormatter.ofPattern("EEEE, d MMMM", ID)
val ShortDateFmt: DateTimeFormatter = DateTimeFormatter.ofPattern("d MMMM", ID)

fun zoneLabel(zone: String) = when (zone) {
    "Asia/Makassar" -> "WITA"
    "Asia/Jayapura" -> "WIT"
    else -> "WIB"
}

/** QR rendered on-device from the Wi-Fi payload (no network call). */
@Composable
fun rememberQr(payload: String, sizePx: Int = 480): ImageBitmap? = remember(payload) {
    runCatching {
        val m = QRCodeWriter().encode(payload, BarcodeFormat.QR_CODE, sizePx, sizePx, mapOf(EncodeHintType.MARGIN to 1))
        val bmp = Bitmap.createBitmap(sizePx, sizePx, Bitmap.Config.RGB_565)
        for (x in 0 until sizePx) for (y in 0 until sizePx) {
            bmp.setPixel(x, y, if (m[x, y]) 0xFF061A2B.toInt() else 0xFFF2F8FB.toInt())
        }
        bmp.asImageBitmap()
    }.getOrNull()
}

/** Remote key hint chip, e.g. [▲ ▼] Pilih */
@Composable
fun KeyHint(key: String, label: String) {
    Row(horizontalArrangement = Arrangement.spacedBy(8.px), verticalAlignment = Alignment.CenterVertically) {
        Box(
            Modifier
                .border(1.px, Sanga.Border2, RoundedCornerShape(7.px))
                .padding(horizontal = 10.px, vertical = 3.px),
        ) { Text(key, style = body(18, 700, Sanga.Text2)) }
        Text(label, style = body(18, 400, Sanga.Text3))
    }
}

fun Modifier.card(shape: Shape, color: Color = Sanga.Card, borderColor: Color = Sanga.Border, borderWidth: Dp) =
    this.background(color, shape).border(borderWidth, borderColor, shape)

