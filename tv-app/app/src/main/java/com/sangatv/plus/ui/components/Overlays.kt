package com.sangatv.plus.ui.components

import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.widthIn
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.animation.core.Animatable
import androidx.compose.animation.core.LinearEasing
import androidx.compose.animation.core.tween
import androidx.compose.foundation.layout.BoxWithConstraints
import androidx.compose.foundation.layout.wrapContentWidth
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableFloatStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.draw.clipToBounds
import androidx.compose.ui.graphics.graphicsLayer
import androidx.compose.ui.layout.onSizeChanged
import androidx.compose.ui.platform.LocalDensity
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.text.style.TextAlign
import androidx.tv.material3.Text
import com.sangatv.plus.R
import com.sangatv.plus.data.model.AnnouncementDto
import com.sangatv.plus.ui.theme.Sanga
import com.sangatv.plus.ui.theme.body
import com.sangatv.plus.ui.theme.px

private val Emergency = Color(0xFFC62828)
private val Warning = Color(0xFFB7791F)

private fun levelColor(a: AnnouncementDto) = when (a.level) {
    "emergency" -> Emergency
    "warning" -> Warning
    else -> Sanga.Accent
}

/** Scrolling bar at the bottom of every screen for "ticker" announcements. */
@Composable
fun AnnouncementTicker(list: List<AnnouncementDto>, modifier: Modifier = Modifier) {
    if (list.isEmpty()) return
    val top = list.firstOrNull { it.isEmergency } ?: list.firstOrNull { it.level == "warning" } ?: list.first()
    val color = levelColor(top)
    val text = list.joinToString("     ◆     ") { "${it.title}: ${it.message.replace('\n', ' ')}" }
    Row(
        modifier
            .fillMaxWidth()
            .background(Sanga.Deepest.copy(alpha = 0.92f))
            .border(1.px, color.copy(alpha = 0.6f))
            .padding(vertical = 9.px),
        verticalAlignment = Alignment.CenterVertically,
    ) {
        Row(
            Modifier.padding(start = 40.px, end = 24.px).background(color, RoundedCornerShape(10.px)).padding(horizontal = 16.px, vertical = 6.px),
            verticalAlignment = Alignment.CenterVertically,
            horizontalArrangement = Arrangement.spacedBy(10.px),
        ) {
            SIcon(SIcons.Megaphone, if (color == Sanga.Accent) Sanga.OnAccent else Color.White, 24.px)
            Text(
                stringResource(if (top.isEmergency) R.string.announcement_emergency else R.string.announcement_label),
                style = body(17, 800, if (color == Sanga.Accent) Sanga.OnAccent else Color.White),
            )
        }
        RunningText(text, Modifier.weight(1f).padding(end = 40.px))
    }
}

/**
 * Classic TV ticker: the text always enters from the right edge and leaves on the left, then
 * repeats, even when it is short (Compose's basicMarquee only scrolls text wider than its box).
 */
@Composable
private fun RunningText(text: String, modifier: Modifier = Modifier) {
    val density = LocalDensity.current
    val speed = 120.px // per second, scaled like the rest of the UI
    BoxWithConstraints(modifier.clipToBounds(), contentAlignment = Alignment.CenterStart) {
        val boxW = constraints.maxWidth.toFloat()
        var textW by remember(text) { mutableFloatStateOf(0f) }
        val offset = remember(text) { Animatable(boxW) }
        LaunchedEffect(text, textW, boxW) {
            if (textW <= 0f || boxW <= 0f) return@LaunchedEffect
            val pxPerSec = with(density) { speed.toPx() }
            while (true) {
                offset.snapTo(boxW)
                val started = System.currentTimeMillis()
                offset.animateTo(-textW, tween(((boxW + textW) / pxPerSec * 1000).toInt(), easing = LinearEasing))
                // Animations switched off on the TV (scale 0): show the text still instead of spinning.
                if (System.currentTimeMillis() - started < 200) { offset.snapTo(0f); break }
            }
        }
        Text(
            text, style = body(24, 600, Sanga.Text), maxLines = 1, softWrap = false,
            modifier = Modifier
                .wrapContentWidth(Alignment.Start, unbounded = true)
                .onSizeChanged { textW = it.width.toFloat() }
                .graphicsLayer { translationX = offset.value },
        )
    }
}

/** Full-screen announcement. OK closes it, except for emergencies (they stay until they end). */
@Composable
fun AnnouncementModal(a: AnnouncementDto) {
    val color = levelColor(a)
    Box(Modifier.fillMaxSize().background(Sanga.Deepest.copy(alpha = 0.9f)), contentAlignment = Alignment.Center) {
        Column(
            Modifier.widthIn(max = 1200.px).card(RoundedCornerShape(32.px), borderColor = color, borderWidth = 3.px).padding(horizontal = 72.px, vertical = 56.px),
            horizontalAlignment = Alignment.CenterHorizontally,
            verticalArrangement = Arrangement.spacedBy(26.px),
        ) {
            Box(Modifier.size(104.px).background(color, RoundedCornerShape(30.px)), contentAlignment = Alignment.Center) {
                SIcon(SIcons.Megaphone, if (color == Sanga.Accent) Sanga.OnAccent else Color.White, 56.px)
            }
            Text(
                stringResource(if (a.isEmergency) R.string.announcement_emergency else R.string.announcement_label),
                style = body(22, 800, color),
            )
            Text(a.title, style = body(50, 800), textAlign = TextAlign.Center)
            Text(a.message, style = body(30, 500, Sanga.Text2), textAlign = TextAlign.Center)
            if (!a.isEmergency) KeyHint("OK", stringResource(R.string.announcement_ok))
        }
    }
}

/** Last-minute warning before the sleep timer turns the TV off. Any key cancels. */
@Composable
fun SleepWarning(secondsLeft: Int, modifier: Modifier = Modifier) {
    Column(
        modifier.card(RoundedCornerShape(28.px), borderWidth = 1.px).padding(horizontal = 56.px, vertical = 40.px),
        horizontalAlignment = Alignment.CenterHorizontally,
        verticalArrangement = Arrangement.spacedBy(16.px),
    ) {
        SIcon(SIcons.Moon, Sanga.Accent, 64.px)
        Text(stringResource(R.string.sleep_warning, secondsLeft), style = body(36, 800))
        Text(stringResource(R.string.sleep_cancel), style = body(22, 500, Sanga.Text3))
    }
}

/** Shown after the sleep timer fired (video stopped, TV going to standby). */
@Composable
fun SleepScreen(standby: Boolean) {
    Box(Modifier.fillMaxSize().background(Color.Black), contentAlignment = Alignment.Center) {
        Column(horizontalAlignment = Alignment.CenterHorizontally, verticalArrangement = Arrangement.spacedBy(18.px)) {
            SIcon(SIcons.Moon, Sanga.Text3, 72.px)
            Text(stringResource(R.string.sleep_now), style = body(40, 700, Sanga.Text3))
            if (!standby) Text(stringResource(R.string.sleep_manual), style = body(22, 400, Sanga.Text3))
        }
    }
}
