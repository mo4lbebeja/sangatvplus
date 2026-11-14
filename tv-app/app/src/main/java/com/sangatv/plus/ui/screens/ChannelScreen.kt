package com.sangatv.plus.ui.screens

import android.app.Activity
import android.view.WindowManager
import androidx.activity.compose.BackHandler
import androidx.compose.animation.AnimatedVisibility
import androidx.compose.animation.fadeIn
import androidx.compose.animation.fadeOut
import androidx.compose.animation.slideInHorizontally
import androidx.compose.animation.slideOutHorizontally
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.focusable
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxHeight
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.layout.widthIn
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.itemsIndexed
import androidx.compose.foundation.lazy.rememberLazyListState
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.runtime.Composable
import androidx.compose.runtime.DisposableEffect
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.collectAsState
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.focus.FocusRequester
import androidx.compose.ui.focus.focusRequester
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.input.key.Key
import androidx.compose.ui.input.key.KeyEventType
import androidx.compose.ui.input.key.key
import androidx.compose.ui.input.key.onKeyEvent
import androidx.compose.ui.input.key.onPreviewKeyEvent
import androidx.compose.ui.input.key.type
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.text.style.TextOverflow
import androidx.media3.ui.compose.PlayerSurface
import androidx.tv.material3.Text
import com.sangatv.plus.R
import com.sangatv.plus.data.model.ChannelDto
import com.sangatv.plus.data.model.ChannelsResponse
import com.sangatv.plus.player.PlayerController
import com.sangatv.plus.ui.components.Focusable
import com.sangatv.plus.ui.components.KeyHint
import com.sangatv.plus.ui.components.SIcon
import com.sangatv.plus.ui.components.SIcons
import com.sangatv.plus.ui.components.TimeFmt
import com.sangatv.plus.ui.components.rememberNow
import com.sangatv.plus.ui.theme.Sanga
import com.sangatv.plus.ui.theme.body
import com.sangatv.plus.ui.theme.px
import kotlinx.coroutines.delay

private const val ALL = "__all__"

@Composable
fun ChannelScreen(
    data: ChannelsResponse?,
    zone: String,
    initial: (List<ChannelDto>) -> ChannelDto?,
    onTuned: (ChannelDto) -> Unit,
    onExit: () -> Unit,
) {
    val context = LocalContext.current
    val controller = remember { PlayerController(context) }
    val status by controller.status.collectAsState()

    // Keep the screen on while watching; release the player when leaving.
    DisposableEffect(Unit) {
        val window = (context as? Activity)?.window
        window?.addFlags(WindowManager.LayoutParams.FLAG_KEEP_SCREEN_ON)
        onDispose {
            window?.clearFlags(WindowManager.LayoutParams.FLAG_KEEP_SCREEN_ON)
            controller.release()
        }
    }

    val all = remember(data) { data?.channels.orEmpty().sortedWith(compareBy({ it.sortOrder }, { it.number })) }
    var current by remember { mutableStateOf<ChannelDto?>(null) }
    var previous by remember { mutableStateOf<ChannelDto?>(null) }
    var overlay by remember { mutableStateOf(true) }
    var infoUntil by remember { mutableStateOf(0L) }
    var digits by remember { mutableStateOf("") }

    fun tune(ch: ChannelDto) {
        if (current?.id != ch.id) previous = current
        current = ch
        controller.play(ch)
        onTuned(ch)
        infoUntil = System.currentTimeMillis() + 4000
    }

    // First tune, and re-tune when signed URLs are refreshed.
    LaunchedEffect(all) {
        val cur = current
        val target = if (cur == null) initial(all) else all.firstOrNull { it.id == cur.id }
        if (target != null) {
            if (cur == null) tune(target) else { current = target; controller.play(target) }
        }
    }

    // Number keys: collect digits for 1.5 s, then jump.
    LaunchedEffect(digits) {
        if (digits.isEmpty()) return@LaunchedEffect
        delay(1500)
        all.firstOrNull { it.number == digits.toIntOrNull() }?.let { tune(it) }
        digits = ""
    }

    var tick by remember { mutableStateOf(0L) }
    LaunchedEffect(infoUntil) {
        while (System.currentTimeMillis() < infoUntil) { delay(250); tick = System.currentTimeMillis() }
        tick = System.currentTimeMillis()
    }
    val showInfo = overlay || tick < infoUntil || System.currentTimeMillis() < infoUntil

    fun step(delta: Int) {
        if (all.isEmpty()) return
        val i = all.indexOfFirst { it.id == current?.id }.coerceAtLeast(0)
        tune(all[(i + delta + all.size) % all.size])
    }

    // BACK: closes the channel list if open, otherwise returns to Home.
    // Previous channel is on the remote's LAST/RECALL key instead.
    BackHandler {
        if (overlay && current != null) overlay = false else onExit()
    }

    val watchFocus = remember { FocusRequester() }
    LaunchedEffect(overlay) { if (!overlay) runCatching { watchFocus.requestFocus() } }

    Box(
        Modifier
            .fillMaxSize()
            .background(Sanga.Deepest)
            .onPreviewKeyEvent { e ->
                if (e.type != KeyEventType.KeyDown) return@onPreviewKeyEvent false
                val d = digitOf(e.key)
                if (d != null) { digits = (digits + d).takeLast(4); true } else false
            },
    ) {
        PlayerSurface(player = controller.player, modifier = Modifier.fillMaxSize())

        // Watching mode: invisible focus target for channel up/down and OK.
        Box(
            Modifier
                .fillMaxSize()
                .focusRequester(watchFocus)
                .onKeyEvent { e ->
                    if (overlay || e.type != KeyEventType.KeyDown) return@onKeyEvent false
                    when (e.key) {
                        Key.DirectionUp, Key.ChannelUp -> { step(+1); true }
                        Key.DirectionDown, Key.ChannelDown -> { step(-1); true }
                        Key.DirectionCenter, Key.Enter, Key.Menu, Key.DirectionLeft -> { overlay = true; true }
                        Key.DirectionRight, Key.Info -> { infoUntil = System.currentTimeMillis() + 4000; true }
                        Key.LastChannel -> { previous?.let { tune(it) }; true }
                        else -> false
                    }
                }
                .focusable(),
        )

        StatusMessage(status, Modifier.align(Alignment.Center))

        if (digits.isNotEmpty()) {
            Text(
                digits, style = body(96, 800, Sanga.Accent),
                modifier = Modifier.align(Alignment.TopEnd).padding(top = 64.px, end = 96.px)
                    .background(Sanga.Background.copy(alpha = 0.85f), RoundedCornerShape(24.px)).padding(horizontal = 36.px, vertical = 12.px),
            )
        }

        AnimatedVisibility(
            visible = overlay,
            enter = slideInHorizontally { -it } + fadeIn(),
            exit = slideOutHorizontally { -it } + fadeOut(),
        ) {
            ChannelPanel(
                data = data,
                all = all,
                current = current,
                zone = zone,
                onPick = { tune(it); overlay = false },
                onBack = onExit,
            )
        }

        AnimatedVisibility(
            visible = showInfo && current != null,
            enter = fadeIn(), exit = fadeOut(),
            modifier = Modifier.align(Alignment.BottomEnd),
        ) {
            current?.let { InfoBar(it, status, Modifier.padding(start = if (overlay) 808.px else 48.px, end = 48.px, bottom = 48.px)) }
        }
    }
}

private fun digitOf(key: Key): Char? = when (key) {
    Key.Zero, Key.NumPad0 -> '0'; Key.One, Key.NumPad1 -> '1'; Key.Two, Key.NumPad2 -> '2'
    Key.Three, Key.NumPad3 -> '3'; Key.Four, Key.NumPad4 -> '4'; Key.Five, Key.NumPad5 -> '5'
    Key.Six, Key.NumPad6 -> '6'; Key.Seven, Key.NumPad7 -> '7'; Key.Eight, Key.NumPad8 -> '8'
    Key.Nine, Key.NumPad9 -> '9'; else -> null
}

@Composable
private fun StatusMessage(status: PlayerController.Status, modifier: Modifier) {
    val msg = when (status) {
        PlayerController.Status.FailingOver -> stringResource(R.string.channel_error)
        PlayerController.Status.Failed -> stringResource(R.string.channel_failed)
        else -> null
    } ?: return
    Text(
        msg, style = body(26, 600, Sanga.Text2),
        modifier = modifier.background(Sanga.Background.copy(alpha = 0.85f), RoundedCornerShape(18.px)).padding(horizontal = 32.px, vertical = 18.px),
    )
}

@Composable
private fun ChannelPanel(
    data: ChannelsResponse?,
    all: List<ChannelDto>,
    current: ChannelDto?,
    zone: String,
    onPick: (ChannelDto) -> Unit,
    onBack: () -> Unit,
) {
    val now by rememberNow(zone)
    val cats = remember(data) { listOf(ALL to "") + data?.categories.orEmpty().map { it.slug to it.name } }
    var cat by remember { mutableStateOf(ALL) }
    val list = remember(all, cat) { if (cat == ALL) all else all.filter { it.categorySlug == cat } }

    fun moveCat(delta: Int) {
        val i = cats.indexOfFirst { it.first == cat }
        cat = cats[(i + delta + cats.size) % cats.size].first
    }

    val listState = rememberLazyListState()
    val focusReqs = remember(list) { List(list.size) { FocusRequester() } }
    LaunchedEffect(list) {
        val idx = list.indexOfFirst { it.id == current?.id }.coerceAtLeast(0)
        if (list.isNotEmpty()) {
            listState.scrollToItem(idx)
            delay(50)
            runCatching { focusReqs[idx].requestFocus() }
        }
    }

    Column(
        Modifier
            .width(760.px)
            .fillMaxHeight()
            .background(Sanga.Background.copy(alpha = 0.94f))
            .padding(start = 72.px, top = 56.px, end = 48.px, bottom = 40.px)
            .onPreviewKeyEvent { e ->
                if (e.type != KeyEventType.KeyDown) return@onPreviewKeyEvent false
                when (e.key) {
                    Key.DirectionLeft -> { moveCat(-1); true }
                    Key.DirectionRight -> { moveCat(+1); true }
                    else -> false
                }
            },
        verticalArrangement = Arrangement.spacedBy(28.px),
    ) {
        Row(Modifier.fillMaxWidth(), verticalAlignment = Alignment.CenterVertically) {
            Box(
                Modifier.size(52.px).background(Sanga.Card, RoundedCornerShape(14.px)),
                contentAlignment = Alignment.Center,
            ) { SIcon(SIcons.ChevronLeft, Sanga.Text2, 26.px) }
            Text(stringResource(R.string.channel_list), style = body(38, 800), modifier = Modifier.padding(start = 16.px).weight(1f))
            Text(now.format(TimeFmt), style = body(30, 700, Sanga.Text2))
        }

        Row(horizontalArrangement = Arrangement.spacedBy(12.px)) {
            cats.forEach { (slug, name) ->
                val on = slug == cat
                Text(
                    if (slug == ALL) stringResource(R.string.cat_all) else name,
                    style = body(20, 600, if (on) Sanga.Background else Sanga.Text2),
                    maxLines = 1,
                    modifier = Modifier
                        .background(if (on) Sanga.BarLight else Color.Transparent, RoundedCornerShape(999.px))
                        .border(1.px, if (on) Sanga.BarLight else Sanga.Border2, RoundedCornerShape(999.px))
                        .padding(horizontal = 22.px, vertical = 10.px),
                )
            }
        }

        if (list.isEmpty()) {
            Text(stringResource(R.string.no_channels), style = body(24, 500, Sanga.Text3), modifier = Modifier.weight(1f))
        } else {
            LazyColumn(state = listState, modifier = Modifier.weight(1f), verticalArrangement = Arrangement.spacedBy(10.px)) {
                itemsIndexed(list, key = { _, c -> c.id }) { i, ch ->
                    Focusable(onClick = { onPick(ch) }, scaleOnFocus = false, modifier = Modifier.focusRequester(focusReqs[i])) { focused ->
                        ChannelRow(ch, focused, playing = ch.id == current?.id)
                    }
                }
            }
        }

        Row(horizontalArrangement = Arrangement.spacedBy(24.px)) {
            KeyHint("▲ ▼", stringResource(R.string.hint_select))
            KeyHint("◀ ▶", stringResource(R.string.hint_category))
            KeyHint("OK", stringResource(R.string.hint_watch))
            KeyHint("BACK", stringResource(R.string.hint_close))
        }
    }
}

@Composable
private fun ChannelRow(ch: ChannelDto, focused: Boolean, playing: Boolean) {
    val fg = if (focused) Sanga.OnAccent else Sanga.Text
    val sub = if (focused) Sanga.OnAccent.copy(alpha = 0.78f) else Sanga.Text3
    Row(
        Modifier
            .fillMaxWidth()
            .background(if (focused) Sanga.Accent else if (playing) Sanga.Card else Color.Transparent, RoundedCornerShape(18.px))
            .padding(horizontal = 22.px, vertical = 16.px),
        verticalAlignment = Alignment.CenterVertically,
        horizontalArrangement = Arrangement.spacedBy(24.px),
    ) {
        Text("%03d".format(ch.number), style = body(26, 800, if (focused) Sanga.OnAccent else Sanga.Text3), modifier = Modifier.widthIn(min = 60.px))
        Column(Modifier.weight(1f), verticalArrangement = Arrangement.spacedBy(4.px)) {
            Text(ch.name, style = body(25, 700, fg), maxLines = 1, overflow = TextOverflow.Ellipsis)
            Text(ch.category ?: "", style = body(17, 400, sub), maxLines = 1)
        }
        val badge = if (ch.isProvider) Sanga.Accent else Color(0xFF3E6C90)
        Text(
            stringResource(if (ch.isProvider) R.string.src_provider else R.string.src_local),
            style = body(15, 700, if (focused) Sanga.OnAccent else if (ch.isProvider) Sanga.Accent else Sanga.Text2),
            modifier = Modifier
                .border(1.px, if (focused) Sanga.OnAccent.copy(alpha = 0.5f) else badge, RoundedCornerShape(8.px))
                .padding(horizontal = 12.px, vertical = 5.px),
        )
    }
}

@Composable
private fun InfoBar(ch: ChannelDto, status: PlayerController.Status, modifier: Modifier) {
    Row(
        modifier
            .fillMaxWidth()
            .background(Sanga.Background.copy(alpha = 0.92f), RoundedCornerShape(24.px))
            .border(1.px, Sanga.Surface2, RoundedCornerShape(24.px))
            .padding(horizontal = 36.px, vertical = 28.px),
        verticalAlignment = Alignment.CenterVertically,
        horizontalArrangement = Arrangement.spacedBy(32.px),
    ) {
        Text("%03d".format(ch.number), style = body(56, 800, Sanga.Accent))
        Column(Modifier.weight(1f), verticalArrangement = Arrangement.spacedBy(10.px)) {
            Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(16.px)) {
                Text(ch.name, style = body(32, 800), maxLines = 1, overflow = TextOverflow.Ellipsis)
                Text(
                    stringResource(if (ch.isProvider) R.string.src_provider_long else R.string.src_local_long),
                    style = body(16, 700, Sanga.Text2),
                    modifier = Modifier.background(Sanga.Surface2, RoundedCornerShape(8.px)).padding(horizontal = 12.px, vertical = 4.px),
                )
            }
            val line = when (status) {
                PlayerController.Status.Buffering -> stringResource(R.string.loading)
                else -> ch.category ?: ""
            }
            Text(line, style = body(20, 400, Sanga.Text3))
        }
    }
}
