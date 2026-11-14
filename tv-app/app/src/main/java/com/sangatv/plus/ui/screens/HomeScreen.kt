package com.sangatv.plus.ui.screens

import androidx.compose.animation.AnimatedContent
import androidx.compose.animation.core.animateDpAsState
import androidx.compose.animation.core.tween
import androidx.compose.animation.fadeIn
import androidx.compose.animation.fadeOut
import androidx.compose.animation.togetherWith
import androidx.compose.foundation.background
import androidx.compose.foundation.focusable
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.BoxScope
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxHeight
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.offset
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.layout.widthIn
import androidx.compose.foundation.shape.GenericShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableIntStateOf
import androidx.compose.runtime.mutableStateListOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.focus.FocusRequester
import androidx.compose.ui.focus.focusRequester
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.input.key.Key
import androidx.compose.ui.input.key.KeyEventType
import androidx.compose.ui.input.key.key
import androidx.compose.ui.input.key.onPreviewKeyEvent
import androidx.compose.ui.input.key.type
import androidx.compose.ui.layout.ContentScale
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.text.style.TextOverflow
import androidx.tv.material3.Text
import coil3.compose.AsyncImage
import com.sangatv.plus.R
import com.sangatv.plus.data.model.Bootstrap
import com.sangatv.plus.data.model.SlideDto
import com.sangatv.plus.player.HomeMusicPlayer
import com.sangatv.plus.ui.InfoSection
import com.sangatv.plus.ui.Screen
import com.sangatv.plus.ui.components.DateFmt
import com.sangatv.plus.ui.components.Focusable
import com.sangatv.plus.ui.components.SIcon
import com.sangatv.plus.ui.components.SIcons
import com.sangatv.plus.ui.components.ShortDateFmt
import com.sangatv.plus.ui.components.TimeFmt
import com.sangatv.plus.ui.components.rememberNow
import com.sangatv.plus.ui.components.zoneLabel
import com.sangatv.plus.ui.theme.Sanga
import com.sangatv.plus.ui.theme.body
import com.sangatv.plus.ui.theme.display
import com.sangatv.plus.ui.theme.px
import kotlinx.coroutines.delay
import kotlin.math.roundToInt

private val Parallelogram = GenericShape { size, _ ->
    moveTo(size.width * 0.16f, 0f)
    lineTo(size.width, 0f)
    lineTo(size.width * 0.84f, size.height)
    lineTo(0f, size.height)
    close()
}

private data class NavItem(val label: Int, val icon: androidx.compose.ui.graphics.vector.ImageVector, val target: Screen)

/** Secret remote sequence that opens the technician menu: ▲▲▼▼◀▶◀▶ */
private val TECH_SEQUENCE = listOf(Key.DirectionUp, Key.DirectionUp, Key.DirectionDown, Key.DirectionDown, Key.DirectionLeft, Key.DirectionRight, Key.DirectionLeft, Key.DirectionRight)

@Composable
fun HomeScreen(data: Bootstrap?, online: Boolean, onOpen: (Screen) -> Unit) {
    val zone = data?.hotel?.timezone ?: "Asia/Jakarta"
    val now by rememberNow(zone)
    val slides = data?.slides.orEmpty()
    var index by remember { mutableIntStateOf(0) }
    val interval = (data?.config?.slideIntervalSeconds ?: 6).coerceIn(3, 30)

    LaunchedEffect(slides.size, interval) {
        while (slides.size > 1) {
            delay(interval * 1000L)
            index = (index + 1) % slides.size
        }
    }
    val slide = slides.getOrNull(index % slides.size.coerceAtLeast(1))

    val keys = remember { mutableStateListOf<Pair<Key, Long>>() }

    Box(
        Modifier
            .fillMaxSize()
            .background(Sanga.Deepest)
            .onPreviewKeyEvent { e ->
                if (e.type == KeyEventType.KeyDown) {
                    val t = System.currentTimeMillis()
                    keys.add(e.key to t)
                    keys.removeAll { t - it.second > 6000 }
                    while (keys.size > TECH_SEQUENCE.size) keys.removeAt(0)
                    if (keys.map { it.first } == TECH_SEQUENCE) {
                        keys.clear()
                        onOpen(Screen.Technician)
                        return@onPreviewKeyEvent true
                    }
                }
                false
            },
    ) {
        HomeMusicPlayer(data?.homeMusic)
        SlideBackground(slide)

        // Hotel name
        Text(
            (data?.hotel?.name ?: "").uppercase(),
            style = display(30, 0.18f),
            modifier = Modifier.offset(96.px, 72.px),
        )

        ClockWeather(data, now, Modifier.align(Alignment.TopEnd).padding(top = 72.px, end = 96.px))

        // Slide text
        AnimatedContent(
            targetState = slide,
            transitionSpec = { fadeIn(tween(300)) togetherWith fadeOut(tween(300)) },
            modifier = Modifier.offset(96.px, 330.px).width(1000.px),
            label = "slide-text",
        ) { s ->
            if (s != null) {
                Column(verticalArrangement = Arrangement.spacedBy(26.px)) {
                    s.kicker?.let { Text(it.uppercase(), style = display(40, 0.1f, Sanga.Text2)) }
                    Text(s.title.uppercase(), style = display(58, 0.06f).copy(lineHeight = display(58).fontSize * 1.25f), maxLines = 3)
                    Box(Modifier.padding(top = 8.px).width(340.px).height(2.px).background(Sanga.Accent))
                    s.description?.let {
                        Text(it, style = body(26, 400, Sanga.Text2).copy(lineHeight = body(26).fontSize * 1.5f), modifier = Modifier.widthIn(max = 760.px))
                    }
                }
            }
        }

        SlideDots(slides.size, index, Modifier.align(Alignment.BottomStart).padding(start = 96.px, bottom = 196.px))

        if (!online) {
            Row(
                Modifier.align(Alignment.BottomEnd).padding(end = 96.px, bottom = 192.px),
                verticalAlignment = Alignment.CenterVertically,
                horizontalArrangement = Arrangement.spacedBy(10.px),
            ) {
                SIcon(SIcons.Offline, Sanga.Text3, 22.px)
                Text(stringResource(R.string.offline), style = body(16, 500, Sanga.Text3))
            }
        }

        BottomBar(data, now, onOpen, Modifier.align(Alignment.BottomCenter))
    }
}

@Composable
private fun BoxScope.SlideBackground(slide: SlideDto?) {
    AnimatedContent(
        targetState = slide?.imageUrl,
        transitionSpec = { fadeIn(tween(1200)) togetherWith fadeOut(tween(1200)) },
        modifier = Modifier.matchParentSize(),
        label = "slide-bg",
    ) { url ->
        if (url != null) {
            AsyncImage(model = url, contentDescription = null, contentScale = ContentScale.Crop, modifier = Modifier.fillMaxSize())
        } else {
            Box(Modifier.fillMaxSize().background(Brush.verticalGradient(listOf(Color(0xFF0E3E66), Color(0xFF0B5C86), Color(0xFF8C7A57)))))
        }
    }
    Box(
        Modifier.matchParentSize().background(
            Brush.horizontalGradient(
                0f to Color(0xE0031220), 0.4f to Color(0x8C031220), 0.75f to Color(0x1A031220), 1f to Color(0x1A031220),
            ),
        ),
    )
    Box(Modifier.fillMaxWidth().height(260.px).background(Brush.verticalGradient(listOf(Color(0x99031220), Color(0x00031220)))))
}

@Composable
private fun SlideDots(count: Int, active: Int, modifier: Modifier) {
    if (count <= 1) return
    Row(modifier, horizontalArrangement = Arrangement.spacedBy(12.px), verticalAlignment = Alignment.CenterVertically) {
        repeat(count) { i ->
            val on = i == active % count
            val w by animateDpAsState(if (on) 56.px else 20.px, tween(300), label = "dot")
            Box(
                Modifier.width(w).height(6.px)
                    .background(if (on) Sanga.Accent else Color(0x73F2F8FB), RoundedCornerShape(3.px)),
            )
        }
    }
}

@Composable
private fun ClockWeather(data: Bootstrap?, now: java.time.ZonedDateTime, modifier: Modifier) {
    Column(modifier.width(360.px), verticalArrangement = Arrangement.spacedBy(22.px)) {
        Row(horizontalArrangement = Arrangement.spacedBy(20.px)) {
            SIcon(SIcons.Clock, Sanga.Accent, 44.px, Modifier.padding(top = 8.px))
            Column {
                Row(verticalAlignment = Alignment.Bottom) {
                    Text(now.format(TimeFmt), style = body(64, 300).copy(lineHeight = body(64).fontSize))
                    Text(" " + zoneLabel(data?.hotel?.timezone ?: ""), style = body(22, 400, Sanga.Text2), modifier = Modifier.padding(bottom = 8.px))
                }
                Text(now.format(DateFmt), style = body(22, 400, Sanga.Text2), modifier = Modifier.padding(top = 8.px))
            }
        }
        data?.weather?.let { w ->
            Box(Modifier.fillMaxWidth().height(1.px).background(Sanga.Accent.copy(alpha = 0.55f)))
            Row(horizontalArrangement = Arrangement.spacedBy(20.px)) {
                SIcon(SIcons.Weather, Sanga.Accent, 44.px, Modifier.padding(top = 8.px))
                Column {
                    Text("${w.temperature.roundToInt()}°C", style = body(52, 300).copy(lineHeight = body(52).fontSize))
                    Text(w.description, style = body(22, 400, Sanga.Text2), modifier = Modifier.padding(top = 8.px))
                }
            }
        }
    }
}

@Composable
private fun BottomBar(data: Bootstrap?, now: java.time.ZonedDateTime, onOpen: (Screen) -> Unit, modifier: Modifier) {
    val items = listOf(
        NavItem(R.string.menu_home, SIcons.Home, Screen.Home),
        NavItem(R.string.menu_tv, SIcons.Tv, Screen.Tv),
        NavItem(R.string.menu_info, SIcons.Info, Screen.Info(InfoSection.About)),
        NavItem(R.string.menu_room_service, SIcons.RoomService, Screen.Info(InfoSection.Dining)),
        NavItem(R.string.menu_facilities, SIcons.Facilities, Screen.Info(InfoSection.Facilities)),
        NavItem(R.string.menu_apps, SIcons.Apps, Screen.Apps),
    )
    val first = remember { FocusRequester() }
    LaunchedEffect(Unit) { runCatching { first.requestFocus() } }

    Row(modifier.fillMaxWidth().height(150.px).background(Sanga.BarLight)) {
        Box(Modifier.width(300.px).fillMaxHeight(), contentAlignment = Alignment.Center) {
            val logo = data?.hotel?.logoUrl
            if (logo != null) {
                AsyncImage(model = logo, contentDescription = data.hotel.name, modifier = Modifier.height(72.px).widthIn(max = 250.px))
            } else {
                Text((data?.hotel?.name ?: "").uppercase(), style = display(22, 0.14f, Sanga.NavyActive), maxLines = 2, overflow = TextOverflow.Ellipsis, modifier = Modifier.padding(horizontal = 20.px))
            }
        }
        items.forEachIndexed { i, item ->
            Focusable(
                onClick = { onOpen(item.target) },
                scaleOnFocus = false,
                modifier = Modifier.width(172.px).fillMaxHeight().then(if (i == 0) Modifier.focusRequester(first) else Modifier),
            ) { focused ->
                Column(
                    Modifier.fillMaxSize().background(if (focused) Sanga.NavyActive else Color.Transparent, Parallelogram),
                    horizontalAlignment = Alignment.CenterHorizontally,
                    verticalArrangement = Arrangement.spacedBy(10.px, Alignment.CenterVertically),
                ) {
                    val fg = if (focused) Sanga.Text else Sanga.BarText
                    SIcon(item.icon, fg, 44.px)
                    Text(stringResource(item.label), style = body(22, 600, fg), maxLines = 1)
                    Box(Modifier.width(56.px).height(4.px).background(if (focused) Sanga.Accent else Color.Transparent, RoundedCornerShape(2.px)))
                }
            }
        }
        Focusable(onClick = { onOpen(Screen.Info(InfoSection.Wifi)) }, modifier = Modifier.width(56.px).fillMaxHeight()) { focused ->
            Box(Modifier.fillMaxSize(), contentAlignment = Alignment.Center) {
                SIcon(SIcons.ChevronRight, if (focused) Sanga.Accent else Sanga.BarText, 32.px)
            }
        }
        Column(
            Modifier.weight(1f).fillMaxHeight().padding(start = 24.px, end = 56.px),
            horizontalAlignment = Alignment.End,
            verticalArrangement = Arrangement.spacedBy(14.px, Alignment.CenterVertically),
        ) {
            Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(22.px)) {
                data?.weather?.let { Text("${it.temperature.roundToInt()}°C", style = body(22, 400, Sanga.BarText)) }
                Box(Modifier.width(1.px).height(36.px).background(Sanga.Text3))
                Column(horizontalAlignment = Alignment.End) {
                    Text(now.format(TimeFmt), style = body(26, 700, Sanga.BarText))
                    Text(now.format(ShortDateFmt), style = body(15, 400, Sanga.BarMuted))
                }
            }
            Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(16.px)) {
                data?.room?.let {
                    Text(
                        stringResource(R.string.room_label, it.number),
                        style = body(17, 700, Sanga.Text),
                        modifier = Modifier.background(Sanga.NavyActive, RoundedCornerShape(6.px)).padding(horizontal = 14.px, vertical = 6.px),
                    )
                }
                val guest = data?.guest
                Text(
                    if (guest != null) stringResource(R.string.welcome_guest, guest.displayName) else stringResource(R.string.welcome_default),
                    style = body(24, 500, Sanga.BarText),
                    maxLines = 1,
                    overflow = TextOverflow.Ellipsis,
                    modifier = Modifier.weight(1f, fill = false),
                )
            }
        }
    }
}

