package com.sangatv.plus.ui.screens

import androidx.annotation.StringRes
import androidx.compose.animation.AnimatedContent
import androidx.compose.animation.core.animateDpAsState
import androidx.compose.animation.core.animateFloatAsState
import androidx.compose.animation.core.tween
import androidx.compose.animation.fadeIn
import androidx.compose.animation.fadeOut
import androidx.compose.animation.slideInVertically
import androidx.compose.animation.slideOutVertically
import androidx.compose.animation.togetherWith
import androidx.compose.foundation.Canvas
import androidx.compose.foundation.background
import androidx.compose.foundation.border
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
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableIntStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.draw.scale
import androidx.compose.ui.focus.FocusRequester
import androidx.compose.ui.focus.focusRequester
import androidx.compose.ui.focus.onFocusChanged
import androidx.compose.ui.geometry.Offset
import androidx.compose.ui.geometry.Size
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.drawscope.Stroke
import androidx.compose.ui.graphics.graphicsLayer
import androidx.compose.ui.graphics.vector.ImageVector
import androidx.compose.ui.input.key.Key
import androidx.compose.ui.input.key.KeyEventType
import androidx.compose.ui.input.key.key
import androidx.compose.ui.input.key.onPreviewKeyEvent
import androidx.compose.ui.input.key.type
import androidx.compose.ui.layout.ContentScale
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.Dp
import androidx.tv.material3.Text
import coil3.compose.AsyncImage
import com.sangatv.plus.R
import com.sangatv.plus.data.model.Bootstrap
import com.sangatv.plus.data.model.SlideDto
import com.sangatv.plus.ui.InfoSection
import com.sangatv.plus.ui.Screen
import com.sangatv.plus.ui.components.DateFmt
import com.sangatv.plus.ui.components.Focusable
import com.sangatv.plus.ui.components.SIcon
import com.sangatv.plus.ui.components.SIcons
import com.sangatv.plus.ui.components.TimeFmt
import com.sangatv.plus.ui.components.zoneLabel
import com.sangatv.plus.ui.theme.Sanga
import com.sangatv.plus.ui.theme.body
import com.sangatv.plus.ui.theme.display
import com.sangatv.plus.ui.theme.px
import java.time.ZonedDateTime
import kotlin.math.abs
import kotlin.math.roundToInt

/** What every Beranda design shows. */
internal class HomeUi(
    val data: Bootstrap?,
    val now: ZonedDateTime,
    val slides: List<SlideDto>,
    val slide: SlideDto?,
    val index: Int,
    val onOpen: (Screen) -> Unit,
)

private data class Menu(@StringRes val label: Int, @StringRes val desc: Int, val icon: ImageVector, val target: Screen)

private val MENU = listOf(
    Menu(R.string.menu_tv, R.string.menu_desc_tv, SIcons.Tv, Screen.Tv),
    Menu(R.string.menu_info, R.string.menu_desc_info, SIcons.Info, Screen.Info(InfoSection.About)),
    Menu(R.string.menu_room_service, R.string.menu_desc_room_service, SIcons.RoomService, Screen.Info(InfoSection.Dining)),
    Menu(R.string.menu_facilities, R.string.menu_desc_facilities, SIcons.Facilities, Screen.Info(InfoSection.Facilities)),
    Menu(R.string.info_gallery, R.string.menu_desc_gallery, SIcons.Star, Screen.Info(InfoSection.Gallery)),
    Menu(R.string.menu_apps, R.string.menu_desc_apps, SIcons.Apps, Screen.Apps),
)

@Composable
private fun rememberFirstFocus(): FocusRequester {
    val first = remember { FocusRequester() }
    LaunchedEffect(Unit) { runCatching { first.requestFocus() } }
    return first
}

@Composable
private fun SlidePhoto(url: String?, modifier: Modifier) {
    AnimatedContent(url, transitionSpec = { fadeIn(tween(1000)) togetherWith fadeOut(tween(1000)) }, modifier = modifier, label = "photo") { u ->
        if (u != null) {
            AsyncImage(u, null, contentScale = ContentScale.Crop, modifier = Modifier.fillMaxSize())
        } else {
            Box(Modifier.fillMaxSize().background(Brush.linearGradient(listOf(Sanga.Surface2, Sanga.Deepest))))
        }
    }
}

@Composable
private fun roomText(data: Bootstrap?): String? = data?.room?.number?.let {
    if (it.any(Char::isDigit)) stringResource(R.string.room_label, it) else it
}

@Composable
private fun HotelMark(data: Bootstrap?, color: Color, size: Int, modifier: Modifier = Modifier) {
    val logo = data?.hotel?.logoUrl
    if (logo != null) {
        AsyncImage(logo, data.hotel.name, modifier = modifier.height((size * 2.6f).toInt().px).widthIn(max = 300.px))
    } else {
        Text((data?.hotel?.name ?: "").uppercase(), style = display(size, 0.14f, color), maxLines = 2, modifier = modifier)
    }
}

@Composable
private fun Greeting(data: Bootstrap?, small: Color, big: Color, align: Alignment.Horizontal = Alignment.Start, bigSize: Int = 34) {
    Column(horizontalAlignment = align, verticalArrangement = Arrangement.spacedBy(4.px)) {
        Text(stringResource(R.string.welcome_default), style = body(20, 500, small), maxLines = 1)
        data?.guest?.let { Text(it.displayName, style = display(bigSize, 0.02f, big), maxLines = 1, overflow = TextOverflow.Ellipsis) }
    }
}

private fun temperature(data: Bootstrap?) = data?.weather?.let { "${it.temperature.roundToInt()}°C" }

@Composable
internal fun OfflineBadge(modifier: Modifier) {
    Row(
        modifier.background(Sanga.Deepest.copy(alpha = 0.8f), RoundedCornerShape(20.px)).padding(horizontal = 18.px, vertical = 8.px),
        verticalAlignment = Alignment.CenterVertically,
        horizontalArrangement = Arrangement.spacedBy(10.px),
    ) {
        SIcon(SIcons.Offline, Sanga.Text3, 22.px)
        Text(stringResource(R.string.offline), style = body(16, 500, Sanga.Text3))
    }
}

// ---------------- Emas Klasik: a grand lobby with a golden rail ----------------

@Composable
internal fun BoxScope.RoyalHome(ui: HomeUi) {
    val first = rememberFirstFocus()
    var active by remember { mutableIntStateOf(0) }
    val rowH = 88.px
    val marker by animateDpAsState(rowH * active, tween(260), label = "marker")

    SlidePhoto(ui.slide?.imageUrl, Modifier.matchParentSize().padding(start = 600.px))
    Box(Modifier.matchParentSize().padding(start = 600.px).background(Brush.horizontalGradient(0f to Sanga.Deepest, 0.42f to Sanga.Deepest.copy(alpha = 0.2f), 1f to Color.Transparent)))
    Box(Modifier.matchParentSize().padding(start = 600.px).background(Brush.verticalGradient(0.45f to Color.Transparent, 1f to Sanga.Deepest.copy(alpha = 0.92f))))
    // Double gold frame around the photo.
    Box(Modifier.matchParentSize().padding(start = 628.px, top = 28.px, end = 28.px, bottom = 28.px).border(1.px, Sanga.Accent.copy(alpha = 0.75f)))
    Box(Modifier.matchParentSize().padding(start = 640.px, top = 40.px, end = 40.px, bottom = 40.px).border(1.px, Sanga.Accent.copy(alpha = 0.3f)))

    Column(Modifier.width(600.px).fillMaxHeight().background(Sanga.Deepest).padding(horizontal = 64.px, vertical = 64.px)) {
        HotelMark(ui.data, Sanga.Accent, 30)
        Box(Modifier.padding(top = 26.px, bottom = 26.px).width(96.px).height(2.px).background(Sanga.Accent))
        Greeting(ui.data, Sanga.Text3, Sanga.Text)
        Spacer(Modifier.height(44.px))
        Box {
            Box(Modifier.offset(y = marker).width(4.px).height(rowH).background(Sanga.Accent))
            Column {
                MENU.forEachIndexed { i, m ->
                    val shift by animateDpAsState(if (active == i) 40.px else 24.px, tween(220), label = "shift")
                    Focusable(
                        onClick = { ui.onOpen(m.target) },
                        scaleOnFocus = false,
                        modifier = Modifier.fillMaxWidth().height(rowH).onFocusChanged { if (it.hasFocus) active = i }.then(if (i == 0) Modifier.focusRequester(first) else Modifier),
                    ) { focused ->
                        Row(Modifier.fillMaxSize().padding(start = shift), verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(22.px)) {
                            SIcon(m.icon, if (focused) Sanga.Accent else Sanga.Text3, 34.px)
                            Text(stringResource(m.label), style = display(if (focused) 36 else 30, 0.01f, if (focused) Sanga.Accent else Sanga.Text2), maxLines = 1)
                        }
                    }
                }
            }
        }
        Spacer(Modifier.weight(1f))
        roomText(ui.data)?.let { Text(it.uppercase(), style = body(20, 700, Sanga.Accent)) }
    }

    Column(Modifier.align(Alignment.TopEnd).padding(top = 76.px, end = 92.px), horizontalAlignment = Alignment.End) {
        Text(ui.now.format(TimeFmt), style = display(104, 0f))
        Text(ui.now.format(DateFmt), style = body(24, 400, Sanga.Text2))
        temperature(ui.data)?.let { Text("$it · ${ui.data?.weather?.description.orEmpty()}", style = body(22, 500, Sanga.Accent), modifier = Modifier.padding(top = 8.px)) }
    }

    AnimatedContent(
        active,
        transitionSpec = { (fadeIn(tween(260)) + slideInVertically(tween(260)) { it / 4 }) togetherWith fadeOut(tween(160)) },
        modifier = Modifier.align(Alignment.BottomStart).padding(start = 700.px, bottom = 96.px).widthIn(max = 1000.px),
        label = "royal-title",
    ) { i ->
        Column(verticalArrangement = Arrangement.spacedBy(10.px)) {
            Text(stringResource(MENU[i].label), style = display(84, 0f))
            Text(stringResource(MENU[i].desc), style = body(28, 400, Sanga.Text2))
        }
    }
}

// ---------------- Tropis: leaf cards that open under the remote ----------------

private val LeafShape @Composable get() = RoundedCornerShape(topStart = 72.px, topEnd = 16.px, bottomEnd = 72.px, bottomStart = 16.px)

@Composable
internal fun BoxScope.TropicalHome(ui: HomeUi) {
    val first = rememberFirstFocus()
    var active by remember { mutableIntStateOf(0) }

    SlidePhoto(ui.slide?.imageUrl, Modifier.matchParentSize())
    Box(Modifier.matchParentSize().background(Brush.verticalGradient(0f to Sanga.Deepest.copy(alpha = 0.55f), 0.32f to Color.Transparent, 0.58f to Color.Transparent, 1f to Sanga.Deepest.copy(alpha = 0.95f))))

    Row(Modifier.fillMaxWidth().padding(start = 80.px, end = 80.px, top = 60.px), verticalAlignment = Alignment.CenterVertically) {
        HotelMark(ui.data, Sanga.Text, 30)
        Spacer(Modifier.weight(1f))
        Row(
            Modifier.background(Sanga.Deepest.copy(alpha = 0.62f), RoundedCornerShape(50.px)).border(1.px, Sanga.Accent.copy(alpha = 0.45f), RoundedCornerShape(50.px)).padding(horizontal = 36.px, vertical = 16.px),
            verticalAlignment = Alignment.CenterVertically,
            horizontalArrangement = Arrangement.spacedBy(24.px),
        ) {
            Text(ui.now.format(TimeFmt), style = body(46, 600))
            Column {
                Text(ui.now.format(DateFmt), style = body(20, 500, Sanga.Text2))
                temperature(ui.data)?.let { Text("$it · ${ui.data?.weather?.description.orEmpty()}", style = body(18, 500, Sanga.Accent)) }
            }
        }
    }

    Column(Modifier.align(Alignment.BottomStart).padding(start = 80.px, bottom = 340.px).widthIn(max = 1100.px), verticalArrangement = Arrangement.spacedBy(10.px)) {
        Greeting(ui.data, Sanga.Accent, Sanga.Text, bigSize = 60)
        ui.slide?.let { Text(listOfNotNull(it.kicker, it.title).joinToString(" · "), style = body(28, 500, Sanga.Text2), maxLines = 1, overflow = TextOverflow.Ellipsis) }
    }
    roomText(ui.data)?.let {
        Text(it, style = body(22, 800, Sanga.OnAccent), modifier = Modifier.align(Alignment.BottomEnd).padding(end = 80.px, bottom = 340.px).background(Sanga.Accent, RoundedCornerShape(24.px)).padding(horizontal = 26.px, vertical = 10.px))
    }

    Row(Modifier.align(Alignment.BottomCenter).padding(bottom = 64.px), horizontalArrangement = Arrangement.spacedBy(20.px)) {
        MENU.forEachIndexed { i, m ->
            val w by animateDpAsState(if (active == i) 520.px else 236.px, tween(280), label = "leaf")
            Focusable(
                onClick = { ui.onOpen(m.target) },
                scaleOnFocus = false,
                modifier = Modifier.width(w).height(236.px).onFocusChanged { if (it.hasFocus) active = i }.then(if (i == 0) Modifier.focusRequester(first) else Modifier),
            ) { focused ->
                Column(
                    Modifier.fillMaxSize().clip(LeafShape).background(if (focused) Sanga.Accent else Sanga.Card.copy(alpha = 0.9f)).padding(30.px),
                    verticalArrangement = Arrangement.SpaceBetween,
                ) {
                    SIcon(m.icon, if (focused) Sanga.OnAccent else Sanga.Accent, 56.px)
                    Column(verticalArrangement = Arrangement.spacedBy(6.px)) {
                        Text(stringResource(m.label), style = body(if (focused) 36 else 26, 700, if (focused) Sanga.OnAccent else Sanga.Text), maxLines = 1, overflow = TextOverflow.Ellipsis)
                        if (focused) Text(stringResource(m.desc), style = body(21, 500, Sanga.OnAccent), maxLines = 1, overflow = TextOverflow.Ellipsis)
                    }
                }
            }
        }
    }
}

// ---------------- Senja: a wheel of menus turning along the horizon ----------------

@Composable
internal fun BoxScope.SunsetHome(ui: HomeUi) {
    val focus = rememberFirstFocus()
    var target by remember { mutableIntStateOf(0) }
    val wheel by animateFloatAsState(target.toFloat(), tween(340), label = "wheel")
    val n = MENU.size
    val current = MENU[target.mod(n)]

    SlidePhoto(ui.slide?.imageUrl, Modifier.matchParentSize())
    Box(Modifier.matchParentSize().background(Brush.verticalGradient(0f to Sanga.Deepest.copy(alpha = 0.72f), 0.42f to Sanga.Deepest.copy(alpha = 0.3f), 0.7f to Sanga.Deepest.copy(alpha = 0.82f), 1f to Sanga.Deepest)))

    Box(Modifier.fillMaxWidth().padding(start = 80.px, end = 80.px, top = 60.px)) {
        HotelMark(ui.data, Sanga.Text, 28, Modifier.align(Alignment.CenterStart))
        Column(Modifier.align(Alignment.CenterEnd), horizontalAlignment = Alignment.End) {
            roomText(ui.data)?.let { Text(it, style = body(24, 800, Sanga.Accent)) }
            temperature(ui.data)?.let { Text("$it · ${ui.data?.weather?.description.orEmpty()}", style = body(20, 500, Sanga.Text2)) }
        }
    }

    Column(Modifier.align(Alignment.TopCenter).padding(top = 120.px), horizontalAlignment = Alignment.CenterHorizontally) {
        Text(ui.now.format(TimeFmt), style = body(190, 300).copy(lineHeight = body(190).fontSize))
        Text("${ui.now.format(DateFmt)} · ${zoneLabel(ui.data?.hotel?.timezone ?: "")}", style = body(28, 500, Sanga.Text2))
        Box(Modifier.padding(top = 20.px)) { Greeting(ui.data, Sanga.Accent, Sanga.Text, Alignment.CenterHorizontally, 40) }
    }

    // The horizon the wheel turns on.
    Canvas(Modifier.align(Alignment.BottomCenter).fillMaxWidth().height(420.px)) {
        val r = size.width * 1.05f
        drawArc(Sanga.Accent.copy(alpha = 0.55f), 180f, 180f, false, Offset(size.width / 2 - r, size.height * 0.3f), Size(r * 2, r * 2), style = Stroke(2f))
    }

    Box(
        Modifier.align(Alignment.BottomCenter).fillMaxWidth().height(380.px)
            .focusRequester(focus).focusable()
            .onPreviewKeyEvent { e ->
                when {
                    e.type == KeyEventType.KeyDown && e.key == Key.DirectionLeft -> { target -= 1; true }
                    e.type == KeyEventType.KeyDown && e.key == Key.DirectionRight -> { target += 1; true }
                    e.type == KeyEventType.KeyUp && (e.key == Key.DirectionCenter || e.key == Key.Enter || e.key == Key.NumPadEnter) -> { ui.onOpen(current.target); true }
                    else -> false
                }
            },
    ) {
        MENU.forEachIndexed { i, m ->
            // Shortest way round the wheel: -3 … +3 steps from the centre.
            val d = (i - wheel + n / 2f).mod(n.toFloat()) - n / 2f
            val near = (1f - abs(d) / 3f).coerceIn(0f, 1f)
            val big = 150.px
            Box(
                Modifier.align(Alignment.TopCenter)
                    .offset(x = 300.px * d, y = 26.px + 22.px * d * d)
                    .size(big)
                    .graphicsLayer {
                        val s = 0.62f + 0.38f * near * near
                        scaleX = s
                        scaleY = s
                        alpha = 0.25f + 0.75f * near
                    }
                    .background(if (abs(d) < 0.5f) Sanga.Accent else Sanga.Card, CircleShape)
                    .border(2.px, Sanga.Accent.copy(alpha = 0.7f), CircleShape),
                contentAlignment = Alignment.Center,
            ) { SIcon(m.icon, if (abs(d) < 0.5f) Sanga.OnAccent else Sanga.Accent, 68.px) }
        }
        AnimatedContent(target.mod(n), transitionSpec = { fadeIn(tween(220)) togetherWith fadeOut(tween(120)) }, modifier = Modifier.align(Alignment.BottomCenter).padding(bottom = 44.px), label = "wheel-label") { i ->
            Column(horizontalAlignment = Alignment.CenterHorizontally, verticalArrangement = Arrangement.spacedBy(4.px)) {
                Text(stringResource(MENU[i].label), style = body(44, 700), textAlign = TextAlign.Center)
                Text(stringResource(MENU[i].desc), style = body(24, 400, Sanga.Text2), textAlign = TextAlign.Center)
            }
        }
    }
    Row(Modifier.align(Alignment.BottomStart).padding(start = 80.px, bottom = 60.px), horizontalArrangement = Arrangement.spacedBy(10.px), verticalAlignment = Alignment.CenterVertically) {
        SIcon(SIcons.ChevronLeft, Sanga.Accent, 26.px)
        Text(stringResource(R.string.menu_choose), style = body(20, 500, Sanga.Text3))
        SIcon(SIcons.ChevronRight, Sanga.Accent, 26.px)
    }
}

// ---------------- Nusantara: a mosaic of tiles on a living kawung batik ----------------

@Composable
private fun Kawung(shift: Float, modifier: Modifier) {
    val tile = 132.px
    val line = Sanga.Accent.copy(alpha = 0.13f)
    Canvas(modifier) {
        val s = tile.toPx()
        val r = s * 0.36f
        var y = -s * 2
        while (y < size.height + s) {
            var x = -s * 2 + shift * s
            while (x < size.width + s) {
                for ((dx, dy) in listOf(-r to 0f, r to 0f, 0f to -r, 0f to r)) {
                    drawCircle(line, r * 0.72f, Offset(x + dx, y + dy), style = Stroke(2f))
                }
                drawCircle(line, r * 0.14f, Offset(x, y))
                x += s
            }
            y += s
        }
    }
}

@Composable
private fun MosaicTile(ui: HomeUi, i: Int, active: Boolean, width: Dp, height: Dp, photo: Boolean, onFocus: () -> Unit, first: FocusRequester?) {
    val m = MENU[i]
    val lift by animateFloatAsState(if (active) 1.05f else 1f, tween(200), label = "lift")
    val base = if (i % 2 == 0) Sanga.NavyActive else Sanga.Card
    Focusable(
        onClick = { ui.onOpen(m.target) },
        scaleOnFocus = false,
        modifier = Modifier.width(width).height(height).scale(lift).onFocusChanged { if (it.hasFocus) onFocus() }.then(first?.let { Modifier.focusRequester(it) } ?: Modifier),
    ) { focused ->
        val shape = RoundedCornerShape(10.px)
        Box(Modifier.fillMaxSize().clip(shape).background(base).border(if (focused) 4.px else 1.px, if (focused) Sanga.Accent else Sanga.Border2, shape)) {
            if (photo) {
                SlidePhoto(ui.slide?.imageUrl, Modifier.matchParentSize())
                Box(Modifier.matchParentSize().background(Brush.verticalGradient(0.3f to Color.Transparent, 1f to Sanga.Deepest.copy(alpha = 0.92f))))
            }
            // Diamond ornament in the corner.
            Box(Modifier.align(Alignment.TopEnd).padding(22.px).size(20.px).graphicsLayer { rotationZ = 45f }.background(if (focused) Sanga.Accent else Sanga.Border2))
            Column(Modifier.align(Alignment.BottomStart).padding(28.px), verticalArrangement = Arrangement.spacedBy(8.px)) {
                SIcon(m.icon, Sanga.Accent, 50.px)
                Text(stringResource(m.label), style = display(34, 0f), maxLines = 1, overflow = TextOverflow.Ellipsis)
                if (focused) Text(stringResource(m.desc), style = body(20, 400, Sanga.Text2), maxLines = 1, overflow = TextOverflow.Ellipsis)
            }
        }
    }
}

@Composable
internal fun BoxScope.HeritageHome(ui: HomeUi) {
    val first = rememberFirstFocus()
    var active by remember { mutableIntStateOf(0) }
    val shift by animateFloatAsState(active * 0.16f, tween(500), label = "batik")

    Box(Modifier.matchParentSize().background(Sanga.Background))
    Kawung(shift, Modifier.matchParentSize())

    Column(Modifier.align(Alignment.CenterStart).padding(start = 88.px).width(590.px)) {
        HotelMark(ui.data, Sanga.Accent, 32)
        Box(Modifier.padding(vertical = 30.px).width(120.px).height(3.px).background(Sanga.Accent))
        Greeting(ui.data, Sanga.Text3, Sanga.Text, bigSize = 52)
        Spacer(Modifier.height(50.px))
        Text(ui.now.format(TimeFmt), style = display(124, 0f))
        Text(ui.now.format(DateFmt), style = body(26, 400, Sanga.Text2))
        temperature(ui.data)?.let { Text("$it · ${ui.data?.weather?.description.orEmpty()}", style = body(24, 500, Sanga.Accent), modifier = Modifier.padding(top = 10.px)) }
        roomText(ui.data)?.let {
            Text(it, style = body(22, 800, Sanga.OnAccent), modifier = Modifier.padding(top = 34.px).background(Sanga.Accent, RoundedCornerShape(6.px)).padding(horizontal = 22.px, vertical = 8.px))
        }
    }

    Column(Modifier.align(Alignment.CenterEnd).padding(end = 80.px), verticalArrangement = Arrangement.spacedBy(22.px)) {
        Row(horizontalArrangement = Arrangement.spacedBy(22.px)) {
            MosaicTile(ui, 0, active == 0, 620.px, 400.px, true, { active = 0 }, first)
            MosaicTile(ui, 1, active == 1, 290.px, 400.px, false, { active = 1 }, null)
            MosaicTile(ui, 5, active == 5, 290.px, 400.px, false, { active = 5 }, null)
        }
        Row(horizontalArrangement = Arrangement.spacedBy(22.px)) {
            MosaicTile(ui, 2, active == 2, 385.px, 300.px, false, { active = 2 }, null)
            MosaicTile(ui, 3, active == 3, 385.px, 300.px, false, { active = 3 }, null)
            MosaicTile(ui, 4, active == 4, 386.px, 300.px, false, { active = 4 }, null)
        }
    }
}

// ---------------- Terang: a magazine page; the headline follows the remote ----------------

@Composable
internal fun BoxScope.EditorialHome(ui: HomeUi) {
    val first = rememberFirstFocus()
    var active by remember { mutableIntStateOf(0) }
    val ink = Sanga.BarText
    val muted = Sanga.BarMuted
    val panel by animateDpAsState(760.px + 34.px * active, tween(380), label = "panel")

    Box(Modifier.matchParentSize().background(Sanga.BarLight))

    Box(Modifier.align(Alignment.CenterEnd).width(panel).fillMaxHeight()) {
        SlidePhoto(ui.slide?.imageUrl, Modifier.matchParentSize())
        Box(Modifier.matchParentSize().background(Brush.verticalGradient(0.6f to Color.Transparent, 1f to Color.Black.copy(alpha = 0.72f))))
        Column(Modifier.align(Alignment.BottomEnd).padding(end = 64.px, bottom = 64.px), horizontalAlignment = Alignment.End) {
            Text(ui.now.format(TimeFmt), style = display(112, 0f, Color.White))
            Text(ui.now.format(DateFmt), style = body(24, 500, Color.White))
            temperature(ui.data)?.let { Text("$it · ${ui.data?.weather?.description.orEmpty()}", style = body(22, 500, Color.White)) }
        }
    }

    Column(Modifier.fillMaxHeight().width(1040.px).padding(start = 88.px, top = 72.px, bottom = 96.px)) {
        Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(28.px)) {
            HotelMark(ui.data, ink, 26)
            roomText(ui.data)?.let { Text(it.uppercase(), style = body(20, 800, Sanga.Accent)) }
        }
        Spacer(Modifier.weight(1f))
        Text("0${active + 1} / 0${MENU.size}", style = body(24, 700, Sanga.Accent))
        AnimatedContent(
            active,
            transitionSpec = { (slideInVertically(tween(300)) { it / 2 } + fadeIn(tween(300))) togetherWith (slideOutVertically(tween(200)) { -it / 2 } + fadeOut(tween(200))) },
            label = "headline",
        ) { i ->
            Column(verticalArrangement = Arrangement.spacedBy(10.px)) {
                Text(stringResource(MENU[i].label), style = display(136, -0.01f, ink), maxLines = 1, overflow = TextOverflow.Ellipsis)
                Text(stringResource(MENU[i].desc), style = body(30, 400, muted), maxLines = 1)
            }
        }
        Spacer(Modifier.weight(1f))
        Greeting(ui.data, muted, ink, bigSize = 36)
        Spacer(Modifier.height(36.px))
        Row(horizontalArrangement = Arrangement.spacedBy(30.px)) {
            MENU.forEachIndexed { i, m ->
                Focusable(
                    onClick = { ui.onOpen(m.target) },
                    scaleOnFocus = false,
                    modifier = Modifier.onFocusChanged { if (it.hasFocus) active = i }.then(if (i == 0) Modifier.focusRequester(first) else Modifier),
                ) { focused ->
                    Column(verticalArrangement = Arrangement.spacedBy(10.px)) {
                        Text(stringResource(m.label), style = body(23, if (focused) 800 else 500, if (focused) ink else muted), maxLines = 1)
                        Box(Modifier.width(if (focused) 64.px else 0.px).height(5.px).background(Sanga.Accent))
                    }
                }
            }
        }
    }
}
