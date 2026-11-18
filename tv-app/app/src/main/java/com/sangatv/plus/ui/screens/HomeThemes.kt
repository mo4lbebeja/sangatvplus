package com.sangatv.plus.ui.screens

import androidx.annotation.StringRes
import androidx.compose.animation.AnimatedContent
import androidx.compose.animation.core.animateDpAsState
import androidx.compose.animation.animateColorAsState
import androidx.compose.animation.core.Animatable
import androidx.compose.animation.core.FastOutSlowInEasing
import androidx.compose.animation.core.LinearEasing
import androidx.compose.animation.core.RepeatMode
import androidx.compose.animation.core.animateFloat
import androidx.compose.animation.core.animateFloatAsState
import androidx.compose.animation.core.infiniteRepeatable
import androidx.compose.animation.core.rememberInfiniteTransition
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
import androidx.compose.foundation.shape.GenericShape
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
import androidx.compose.ui.draw.clipToBounds
import androidx.compose.ui.draw.scale
import androidx.compose.ui.focus.FocusRequester
import androidx.compose.ui.focus.focusRequester
import androidx.compose.ui.focus.onFocusChanged
import androidx.compose.ui.geometry.CornerRadius
import androidx.compose.ui.geometry.Offset
import androidx.compose.ui.geometry.Size
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.Path
import androidx.compose.ui.graphics.StrokeCap
import androidx.compose.ui.graphics.TransformOrigin
import androidx.compose.ui.graphics.drawscope.Stroke
import androidx.compose.ui.graphics.drawscope.rotate
import androidx.compose.ui.graphics.graphicsLayer
import androidx.compose.ui.graphics.vector.ImageVector
import androidx.compose.ui.input.key.Key
import androidx.compose.ui.input.key.KeyEventType
import androidx.compose.ui.input.key.key
import androidx.compose.ui.input.key.onPreviewKeyEvent
import androidx.compose.ui.input.key.type
import androidx.compose.ui.layout.ContentScale
import androidx.compose.ui.platform.LocalDensity
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
        AsyncImage(
            logo, data.hotel.name,
            modifier = modifier
                .background(Sanga.BarLight, RoundedCornerShape(12.px)).padding(horizontal = 18.px, vertical = 10.px)
                .height((size * 2.6f).toInt().px).widthIn(max = 300.px),
        )
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
    Box(Modifier.matchParentSize().padding(start = 600.px).background(Brush.verticalGradient(0f to Sanga.Deepest.copy(alpha = 0.72f), 0.34f to Color.Transparent, 0.5f to Color.Transparent, 1f to Sanga.Deepest.copy(alpha = 0.92f))))
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

    Column(
        Modifier.align(Alignment.TopEnd).padding(top = 72.px, end = 76.px)
            .background(Sanga.Deepest.copy(alpha = 0.72f), RoundedCornerShape(8.px)).padding(horizontal = 30.px, vertical = 16.px),
        horizontalAlignment = Alignment.End,
    ) {
        Text(ui.now.format(TimeFmt), style = display(92, 0f))
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
    Box(Modifier.matchParentSize().background(Brush.verticalGradient(0f to Sanga.Deepest.copy(alpha = 0.55f), 0.26f to Color.Transparent, 0.42f to Sanga.Deepest.copy(alpha = 0.2f), 0.62f to Sanga.Deepest.copy(alpha = 0.82f), 1f to Sanga.Deepest)))

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
        Greeting(ui.data, Sanga.Text, Sanga.Text, bigSize = 60)
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
    Box(Modifier.matchParentSize().background(Brush.verticalGradient(0f to Sanga.Deepest.copy(alpha = 0.82f), 0.46f to Sanga.Deepest.copy(alpha = 0.62f), 0.68f to Sanga.Deepest.copy(alpha = 0.88f), 1f to Sanga.Deepest)))

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
                    e.type == KeyEventType.KeyDown && (e.key == Key.DirectionCenter || e.key == Key.Enter || e.key == Key.NumPadEnter) -> { ui.onOpen(current.target); true }
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
                Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(26.px)) {
                    SIcon(SIcons.ChevronLeft, Sanga.Accent, 34.px)
                    Text(stringResource(MENU[i].label), style = body(44, 700), textAlign = TextAlign.Center)
                    SIcon(SIcons.ChevronRight, Sanga.Accent, 34.px)
                }
                Text(stringResource(MENU[i].desc), style = body(24, 400, Sanga.Text2), textAlign = TextAlign.Center)
            }
        }
    }
}

// ---------------- Nusantara: a temple gate of photos on indigo batik ----------------

/** Stepped silhouette of a candi gate; [k] is the size of one step relative to the height. */
private fun gapura(k: Float) = GenericShape { size, _ ->
    val st = size.height * k
    moveTo(0f, size.height)
    lineTo(0f, st * 3)
    lineTo(st, st * 3)
    lineTo(st, st * 2)
    lineTo(st * 2, st * 2)
    lineTo(st * 2, st)
    lineTo(st * 3, st)
    lineTo(st * 3, 0f)
    lineTo(size.width - st * 3, 0f)
    lineTo(size.width - st * 3, st)
    lineTo(size.width - st * 2, st)
    lineTo(size.width - st * 2, st * 2)
    lineTo(size.width - st, st * 2)
    lineTo(size.width - st, st * 3)
    lineTo(size.width, st * 3)
    lineTo(size.width, size.height)
    close()
}

private val GateTile = gapura(0.07f)

@Composable
private fun Kawung(modifier: Modifier) {
    val tile = BATIK_TILE.px
    val line = Sanga.Accent.copy(alpha = 0.2f)
    Canvas(modifier) {
        val s = tile.toPx()
        val r = s * 0.25f
        var y = 0f
        while (y < size.height + s) {
            var x = 0f
            while (x < size.width + s) {
                for ((dx, dy) in listOf(-r to 0f, r to 0f, 0f to -r, 0f to r)) drawCircle(line, r, Offset(x + dx, y + dy), style = Stroke(2f))
                drawCircle(line, r * 0.2f, Offset(x, y))
                x += s
            }
            y += s
        }
    }
}

/** Slowly turning batik medallion behind the clock. */
@Composable
private fun Medallion(modifier: Modifier) {
    val gold = Sanga.Accent.copy(alpha = 0.3f)
    Canvas(modifier) {
        val r = size.minDimension / 2
        for (i in 0 until 16) rotate(i * 22.5f) { drawCircle(gold, r * 0.3f, Offset(center.x, center.y - r * 0.68f), style = Stroke(2f)) }
        for (i in 0 until 8) rotate(i * 45f + 22.5f) { drawCircle(gold, r * 0.17f, Offset(center.x, center.y - r * 0.32f), style = Stroke(2f)) }
        drawCircle(gold, r * 0.98f, style = Stroke(2f))
        drawCircle(gold, r * 0.14f, style = Stroke(2f))
    }
}

/** Tumpal: the row of triangles that edges a batik cloth. */
@Composable
private fun Tumpal(modifier: Modifier) {
    val gold = Sanga.Accent
    val soga = Sanga.NavyActive
    Canvas(modifier) {
        val step = size.width
        var y = 0f
        var n = 0
        while (y < size.height) {
            val t = Path().apply { moveTo(0f, y); lineTo(size.width, y + step / 2); lineTo(0f, y + step); close() }
            drawPath(t, if (n % 2 == 0) gold else soga)
            y += step
            n++
        }
    }
}

private const val BATIK_TILE = 124
private const val BATIK_PANEL = 560
private const val BATIK_ITEM = 219

@Composable
internal fun BoxScope.HeritageHome(ui: HomeUi) {
    val first = rememberFirstFocus()
    var active by remember { mutableIntStateOf(0) }
    val motion = rememberInfiniteTransition(label = "heritage")
    val zoom by motion.animateFloat(1f, 1.1f, infiniteRepeatable(tween(18000, easing = LinearEasing), RepeatMode.Reverse), label = "zoom")
    val drift by motion.animateFloat(0f, 1f, infiniteRepeatable(tween(16000, easing = LinearEasing)), label = "drift")
    val spin by motion.animateFloat(0f, 360f, infiniteRepeatable(tween(120000, easing = LinearEasing)), label = "spin")
    val shine by motion.animateFloat(-1f, 2f, infiniteRepeatable(tween(2600, easing = LinearEasing)), label = "shine")
    val pulse by motion.animateFloat(1f, 1.12f, infiniteRepeatable(tween(720), RepeatMode.Reverse), label = "pulse")

    // Panel and menu glide in when Beranda opens.
    val enter = remember { Animatable(0f) }
    LaunchedEffect(Unit) { enter.animateTo(1f, tween(800, easing = FastOutSlowInEasing)) }
    // Gold line that fills while the current photo is showing.
    val progress = remember { Animatable(0f) }
    val interval = (ui.data?.config?.slideIntervalSeconds ?: 6).coerceIn(3, 30)
    LaunchedEffect(ui.index) { progress.snapTo(0f); progress.animateTo(1f, tween(interval * 1000, easing = LinearEasing)) }
    // Underline that draws itself under each new title.
    val underline = remember { Animatable(0f) }
    LaunchedEffect(active) { underline.snapTo(0f); underline.animateTo(1f, tween(420, easing = FastOutSlowInEasing)) }
    val batikTile = with(LocalDensity.current) { BATIK_TILE.px.toPx() }
    val plaqueWidth = with(LocalDensity.current) { BATIK_ITEM.px.toPx() }
    val plaque by animateDpAsState(BATIK_ITEM.px * active, tween(280, easing = FastOutSlowInEasing), label = "plaque")

    // Photo, slowly moving.
    Box(Modifier.matchParentSize().padding(start = BATIK_PANEL.px).clipToBounds()) {
        SlidePhoto(ui.slide?.imageUrl, Modifier.matchParentSize().graphicsLayer { scaleX = zoom; scaleY = zoom })
        Box(Modifier.matchParentSize().background(Brush.verticalGradient(0f to Sanga.Deepest.copy(alpha = 0.45f), 0.26f to Color.Transparent, 0.48f to Color.Transparent, 0.78f to Sanga.Deepest.copy(alpha = 0.86f), 1f to Sanga.Deepest)))
    }

    roomText(ui.data)?.let {
        Text(it.uppercase(), style = body(21, 800, Sanga.OnAccent), modifier = Modifier.align(Alignment.TopEnd).padding(top = 52.px, end = 60.px).background(Sanga.Accent, RoundedCornerShape(6.px)).padding(horizontal = 22.px, vertical = 8.px))
    }

    AnimatedContent(
        active,
        transitionSpec = { (fadeIn(tween(300)) + slideInVertically(tween(300)) { it / 3 }) togetherWith fadeOut(tween(140)) },
        modifier = Modifier.align(Alignment.BottomStart).padding(start = 680.px, bottom = 262.px),
        label = "batik-title",
    ) { i ->
        Column(verticalArrangement = Arrangement.spacedBy(6.px)) {
            Text(stringResource(MENU[i].label), style = display(88, 0f))
            Text(stringResource(MENU[i].desc), style = body(29, 400, Sanga.Text2))
        }
    }
    Box(Modifier.align(Alignment.BottomStart).padding(start = 680.px, bottom = 238.px).width(260.px).height(4.px).graphicsLayer { scaleX = underline.value; transformOrigin = TransformOrigin(0f, 0.5f) }.background(Sanga.Accent))

    // Menu dock with a gold gate plaque that slides under the remote.
    Box(Modifier.align(Alignment.BottomStart).padding(start = (BATIK_PANEL + 46).px).fillMaxWidth().height(190.px).graphicsLayer { translationY = (1f - enter.value) * size.height }) {
        Box(Modifier.matchParentSize().background(Sanga.Background.copy(alpha = 0.92f)))
        Box(Modifier.fillMaxWidth().height(4.px).graphicsLayer { scaleX = progress.value; transformOrigin = TransformOrigin(0f, 0.5f) }.background(Sanga.Accent))
        Box(Modifier.align(Alignment.BottomStart).offset(x = plaque).width(BATIK_ITEM.px).height(176.px).clip(GateTile).background(Sanga.Accent)) {
            Box(Modifier.fillMaxHeight().width(90.px).graphicsLayer { translationX = shine * plaqueWidth }.background(Brush.horizontalGradient(listOf(Color.Transparent, Color.White.copy(alpha = 0.42f), Color.Transparent))))
        }
        Row {
            MENU.forEachIndexed { i, m ->
                Focusable(
                    onClick = { ui.onOpen(m.target) },
                    scaleOnFocus = false,
                    modifier = Modifier.width(BATIK_ITEM.px).fillMaxHeight().onFocusChanged { if (it.hasFocus) active = i }.then(if (i == 0) Modifier.focusRequester(first) else Modifier),
                ) { focused ->
                    val fg by animateColorAsState(if (focused) Sanga.OnAccent else Sanga.Text, tween(220), label = "fg")
                    Column(Modifier.fillMaxSize().padding(top = 26.px), horizontalAlignment = Alignment.CenterHorizontally, verticalArrangement = Arrangement.spacedBy(12.px, Alignment.CenterVertically)) {
                        SIcon(m.icon, if (focused) fg else Sanga.Accent, 54.px, Modifier.graphicsLayer { val k = if (focused) pulse else 1f; scaleX = k; scaleY = k })
                        Text(stringResource(m.label), style = body(25, 700, fg), maxLines = 1, overflow = TextOverflow.Ellipsis)
                    }
                }
            }
        }
    }

    // Batik panel.
    Box(Modifier.width((BATIK_PANEL + 46).px).fillMaxHeight().graphicsLayer { translationX = (enter.value - 1f) * size.width }) {
        Box(Modifier.width(BATIK_PANEL.px).fillMaxHeight().clipToBounds().background(Brush.verticalGradient(listOf(Sanga.Background, Sanga.Deepest)))) {
            Kawung(Modifier.fillMaxHeight().width((BATIK_PANEL + BATIK_TILE).px).graphicsLayer { translationX = -drift * batikTile })
            Box(Modifier.matchParentSize().background(Brush.verticalGradient(0f to Sanga.Deepest.copy(alpha = 0.3f), 1f to Sanga.Deepest.copy(alpha = 0.82f))))
            Medallion(Modifier.align(Alignment.BottomCenter).offset(y = 150.px).size(640.px).graphicsLayer { rotationZ = spin })
            Column(Modifier.padding(start = 64.px, end = 40.px, top = 60.px)) {
                HotelMark(ui.data, Sanga.Accent, 30)
                Spacer(Modifier.height(54.px))
                Greeting(ui.data, Sanga.Accent, Sanga.Text, bigSize = 50)
                Box(Modifier.padding(vertical = 34.px).width(120.px).height(2.px).background(Sanga.Accent))
                Text(ui.now.format(TimeFmt), style = display(128, 0f))
                Text(ui.now.format(DateFmt), style = body(27, 500, Sanga.Text2))
                temperature(ui.data)?.let { Text("$it · ${ui.data?.weather?.description.orEmpty()}", style = body(23, 500, Sanga.Accent), modifier = Modifier.padding(top = 10.px)) }
            }
        }
        Tumpal(Modifier.align(Alignment.CenterEnd).width(46.px).fillMaxHeight())
    }
}

// ---------------- Daerah: Pasundan, under a Julang Ngapak roof with a set of angklung ----------------

/** Bilik: the woven bamboo walls of a Sundanese house. */
@Composable
private fun Bilik(modifier: Modifier) {
    val strip = Sanga.Accent.copy(alpha = 0.1f)
    val cell = 56.px
    Canvas(modifier) {
        val s = cell.toPx()
        var row = 0
        var y = 0f
        while (y < size.height) {
            var col = 0
            var x = 0f
            while (x < size.width) {
                for (k in 1..3) {
                    val o = s * k / 4f
                    if ((row + col) % 2 == 0) drawLine(strip, Offset(x, y + o), Offset(x + s, y + o), 3f)
                    else drawLine(strip, Offset(x + o, y), Offset(x + o, y + s), 3f)
                }
                x += s
                col++
            }
            y += s
            row++
        }
    }
}

/** Kujang, the emblem of West Java. */
@Composable
private fun Kujang(color: Color, hole: Color, modifier: Modifier) {
    Canvas(modifier) {
        val u = size.height / 220f
        val blade = Path().apply {
            moveTo(46 * u, 216 * u)
            lineTo(62 * u, 216 * u)
            lineTo(59 * u, 152 * u)
            cubicTo(92 * u, 132 * u, 102 * u, 92 * u, 72 * u, 62 * u)
            cubicTo(61 * u, 46 * u, 63 * u, 22 * u, 80 * u, 2 * u)
            cubicTo(46 * u, 15 * u, 28 * u, 40 * u, 32 * u, 68 * u)
            lineTo(14 * u, 60 * u)
            lineTo(29 * u, 92 * u)
            cubicTo(27 * u, 116 * u, 37 * u, 136 * u, 47 * u, 152 * u)
            close()
        }
        drawPath(blade, color)
        for ((x, y) in listOf(52f to 72f, 58f to 96f, 58f to 120f)) drawCircle(hole, 5.5f * u, Offset(x * u, y * u))
    }
}

/** Julang Ngapak: a roof spread like a bird's wings, with the crossed "capit gunting" at both ends. */
@Composable
private fun JulangNgapak(modifier: Modifier) {
    val gold = Sanga.Accent
    val thatch = Sanga.Deepest
    Canvas(modifier) {
        val w = size.width
        val h = size.height
        val ridge = h * 0.34f
        val inset = w * 0.13f
        val roof = Path().apply {
            moveTo(0f, h)
            lineTo(inset * 0.48f, h * 0.76f)
            lineTo(inset, ridge)
            lineTo(w - inset, ridge)
            lineTo(w - inset * 0.48f, h * 0.76f)
            lineTo(w, h)
            lineTo(w - inset * 0.55f, h * 0.9f)
            lineTo(inset * 0.55f, h * 0.9f)
            close()
        }
        drawPath(roof, thatch)
        var x = inset
        while (x < w - inset) {
            drawLine(gold.copy(alpha = 0.3f), Offset(x, ridge + 8f), Offset(x, h * 0.86f), 2f)
            x += w / 64f
        }
        drawPath(roof, gold, style = Stroke(4f))
        for (cx in listOf(inset, w - inset)) {
            val r = h * 0.3f
            drawLine(gold, Offset(cx - r, ridge - r), Offset(cx + r * 0.7f, ridge + r * 0.7f), 9f, StrokeCap.Round)
            drawLine(gold, Offset(cx + r, ridge - r), Offset(cx - r * 0.7f, ridge + r * 0.7f), 9f, StrokeCap.Round)
        }
    }
}

/** One angklung of the set: taller to the right, like the notes of a scale. */
@Composable
private fun Angklung(step: Int, on: Boolean, modifier: Modifier) {
    val bamboo = if (on) Sanga.Accent else Color(0xFFC39A5F)
    val dark = Sanga.Deepest
    Canvas(modifier) {
        val w = size.width
        val h = size.height
        val grow = 0.62f + step * 0.06f
        for (x in listOf(0.16f, 0.81f)) drawRect(bamboo, Offset(w * x, h * 0.02f), Size(w * 0.035f, h * 0.92f))
        for (y in listOf(0.14f, 0.58f)) drawRect(bamboo, Offset(w * 0.12f, h * y), Size(w * 0.76f, h * 0.03f))
        listOf(0.27f to grow, 0.44f to grow * 0.86f, 0.61f to grow * 0.72f).forEach { (x, tall) ->
            val top = h * (0.94f - tall * 0.88f)
            val tube = Size(w * 0.13f, h * 0.94f - top)
            drawRoundRect(bamboo, Offset(w * x, top), tube, CornerRadius(w * 0.03f))
            drawLine(dark, Offset(w * x, top + tube.height * 0.5f), Offset(w * (x + 0.13f), top + tube.height * 0.5f), 3f)
            drawLine(dark, Offset(w * (x + 0.065f), top + 6f), Offset(w * (x + 0.065f), top + tube.height * 0.3f), 3f)
        }
        drawRoundRect(bamboo, Offset(w * 0.08f, h * 0.92f), Size(w * 0.84f, h * 0.08f), CornerRadius(h * 0.03f))
    }
}

@Composable
internal fun BoxScope.SundaHome(ui: HomeUi) {
    val first = rememberFirstFocus()
    var active by remember { mutableIntStateOf(0) }
    // The chosen angklung trembles as if it were being played.
    val shake by rememberInfiniteTransition(label = "angklung").animateFloat(-3.5f, 3.5f, infiniteRepeatable(tween(130), RepeatMode.Reverse), label = "shake")

    Box(Modifier.matchParentSize().background(Sanga.Background))
    Bilik(Modifier.matchParentSize())
    Box(Modifier.matchParentSize().background(Brush.horizontalGradient(0f to Sanga.Deepest.copy(alpha = 0.86f), 0.34f to Sanga.Deepest.copy(alpha = 0.3f), 1f to Color.Transparent)))

    Column(Modifier.padding(start = 72.px, top = 44.px).width(510.px)) {
        HotelMark(ui.data, Sanga.Accent, 26)
        Row(Modifier.padding(top = 26.px), verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(16.px)) {
            Kujang(Sanga.Accent, Sanga.Background, Modifier.width(92.px).height(202.px))
            Column {
                Text("Wilujeng", style = display(58, 0f, Sanga.Accent))
                Text("Sumping", style = display(58, 0f, Sanga.Accent))
                ui.data?.guest?.let { Text(it.displayName, style = body(30, 700), maxLines = 1, overflow = TextOverflow.Ellipsis) }
            }
        }
        Text(ui.now.format(TimeFmt), style = display(92, 0f), modifier = Modifier.padding(top = 18.px))
        Text(ui.now.format(DateFmt), style = body(24, 500, Sanga.Text2))
        temperature(ui.data)?.let { Text("$it · ${ui.data?.weather?.description.orEmpty()}", style = body(21, 500, Sanga.Accent)) }
        roomText(ui.data)?.let { Text(it.uppercase(), style = body(20, 800, Sanga.Accent), modifier = Modifier.padding(top = 8.px)) }
    }

    // The house: photo under the roof.
    Box(Modifier.align(Alignment.TopEnd).padding(top = 14.px, end = 40.px).width(1300.px).height(812.px)) {
        Box(Modifier.align(Alignment.BottomCenter).padding(horizontal = 56.px).fillMaxWidth().height(674.px).border(3.px, Sanga.Accent).padding(10.px)) {
            SlidePhoto(ui.slide?.imageUrl, Modifier.matchParentSize())
            Box(Modifier.matchParentSize().background(Brush.verticalGradient(0.42f to Color.Transparent, 1f to Sanga.Deepest.copy(alpha = 0.95f))))
            AnimatedContent(
                active,
                transitionSpec = { (fadeIn(tween(240)) + slideInVertically(tween(240)) { it / 4 }) togetherWith fadeOut(tween(140)) },
                modifier = Modifier.align(Alignment.BottomStart).padding(start = 44.px, bottom = 28.px),
                label = "sunda-title",
            ) { i ->
                Column {
                    Text(stringResource(MENU[i].label), style = display(66, 0f))
                    Text(stringResource(MENU[i].desc), style = body(26, 400, Sanga.Text2))
                }
            }
        }
        JulangNgapak(Modifier.align(Alignment.TopCenter).fillMaxWidth().height(190.px))
    }

    Row(Modifier.align(Alignment.BottomCenter).padding(bottom = 18.px), horizontalArrangement = Arrangement.spacedBy(20.px), verticalAlignment = Alignment.Bottom) {
        MENU.forEachIndexed { i, m ->
            Focusable(
                onClick = { ui.onOpen(m.target) },
                scaleOnFocus = false,
                modifier = Modifier.width(284.px).height(214.px).onFocusChanged { if (it.hasFocus) active = i }.then(if (i == 0) Modifier.focusRequester(first) else Modifier),
            ) { focused ->
                Column(horizontalAlignment = Alignment.CenterHorizontally, verticalArrangement = Arrangement.Bottom, modifier = Modifier.fillMaxSize()) {
                    Angklung(i, focused, Modifier.width(112.px).height(130.px).graphicsLayer { rotationZ = if (focused) shake else 0f; transformOrigin = TransformOrigin(0.5f, 1f) })
                    Row(
                        Modifier.padding(top = 8.px).fillMaxWidth().height(72.px).background(if (focused) Sanga.Accent else Sanga.Deepest.copy(alpha = 0.86f), RoundedCornerShape(14.px)).border(2.px, Sanga.Accent.copy(alpha = if (focused) 1f else 0.55f), RoundedCornerShape(14.px)).padding(horizontal = 18.px),
                        verticalAlignment = Alignment.CenterVertically,
                        horizontalArrangement = Arrangement.spacedBy(12.px, Alignment.CenterHorizontally),
                    ) {
                        SIcon(m.icon, if (focused) Sanga.OnAccent else Sanga.Accent, 36.px)
                        Text(stringResource(m.label), style = body(26, 800, if (focused) Sanga.OnAccent else Sanga.Text), maxLines = 1, overflow = TextOverflow.Ellipsis)
                    }
                }
            }
        }
    }
}
