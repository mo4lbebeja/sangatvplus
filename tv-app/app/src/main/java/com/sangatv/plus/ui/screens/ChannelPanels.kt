package com.sangatv.plus.ui.screens

import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxHeight
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.heightIn
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.layout.widthIn
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.itemsIndexed
import androidx.compose.foundation.lazy.rememberLazyListState
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.runtime.Composable
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
import androidx.compose.ui.focus.onFocusChanged
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.vector.ImageVector
import androidx.compose.ui.input.key.Key
import androidx.compose.ui.input.key.KeyEventType
import androidx.compose.ui.input.key.key
import androidx.compose.ui.input.key.nativeKeyCode
import androidx.compose.ui.input.key.onPreviewKeyEvent
import androidx.compose.ui.input.key.type
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.text.style.TextOverflow
import androidx.tv.material3.Text
import com.sangatv.plus.R
import com.sangatv.plus.data.model.ChannelDto
import com.sangatv.plus.data.model.PinHash
import com.sangatv.plus.device.AdultLock
import com.sangatv.plus.device.SleepTimer
import com.sangatv.plus.player.PlayerController
import com.sangatv.plus.ui.components.Focusable
import com.sangatv.plus.ui.components.KeyHint
import com.sangatv.plus.ui.components.SIcon
import com.sangatv.plus.ui.components.SIcons
import com.sangatv.plus.ui.components.card
import com.sangatv.plus.ui.theme.Sanga
import com.sangatv.plus.ui.theme.body
import com.sangatv.plus.ui.theme.px
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.delay
import kotlinx.coroutines.withContext

// ---------------------------------------------------------------- Options (▶ while watching)

@Composable
internal fun OptionsPanel(
    channel: ChannelDto,
    guide: Guide,
    nowMs: Long,
    controller: PlayerController,
    isFavorite: Boolean,
    previous: ChannelDto?,
    onGuide: () -> Unit,
    onToggleFavorite: () -> Unit,
    onPrevious: () -> Unit,
    onToast: (String) -> Unit,
    onClose: () -> Unit,
    bottomInset: androidx.compose.ui.unit.Dp = 0.px,
) {
    val context = LocalContext.current
    val audio by controller.audioTracks.collectAsState()
    val text by controller.textTracks.collectAsState()
    val sleepEnds by SleepTimer.endsAt.collectAsState()
    val (now, _) = guide.nowNext(channel, nowMs)
    val off = stringResource(R.string.off)

    data class Item(val icon: ImageVector, val label: String, val value: String?, val action: () -> Unit)

    val items = buildList {
        add(Item(SIcons.Calendar, stringResource(R.string.opt_guide), null, onGuide))
        add(Item(SIcons.Star, stringResource(if (isFavorite) R.string.opt_fav_remove else R.string.opt_fav_add), null, onToggleFavorite))
        if (audio.size > 1) {
            val sel = audio.indexOfFirst { it.selected }
            add(Item(SIcons.Speaker, stringResource(R.string.opt_audio), audio.getOrNull(sel)?.label ?: stringResource(R.string.default_track)) {
                val next = audio[(sel + 1).mod(audio.size)]
                controller.selectAudio(next)
                onToast(next.label)
            })
        }
        if (text.isNotEmpty()) {
            val sel = text.indexOfFirst { it.selected }
            add(Item(SIcons.Subtitles, stringResource(R.string.opt_subtitle), text.getOrNull(sel)?.label ?: off) {
                // Cycle: off → track 1 → … → off
                val next = if (sel + 1 < text.size) text[sel + 1] else null
                controller.selectText(next)
                onToast(next?.label ?: off)
            })
        }
        if (previous != null) add(Item(SIcons.Recall, stringResource(R.string.opt_previous), "%03d %s".format(previous.number, previous.name), onPrevious))
        val minutes = if (sleepEnds == 0L) 0 else SleepTimer.minutesLeft
        add(Item(SIcons.Moon, stringResource(R.string.opt_sleep), if (minutes == 0) off else context.getString(R.string.minutes_fmt, minutes)) {
            val m = SleepTimer.cycle()
            onToast(if (m == 0) context.getString(R.string.sleep_off) else context.getString(R.string.sleep_set, m))
        })
    }

    val first = remember { FocusRequester() }
    LaunchedEffect(Unit) { delay(60); runCatching { first.requestFocus() } }

    Column(
        Modifier
            .width(680.px)
            .fillMaxHeight()
            .background(Sanga.Background.copy(alpha = 0.95f))
            .padding(start = 48.px, top = 56.px, end = 64.px, bottom = 40.px + bottomInset)
            .onPreviewKeyEvent { e -> if (e.type == KeyEventType.KeyDown && e.key == Key.DirectionLeft) { onClose(); true } else false },
        verticalArrangement = Arrangement.spacedBy(18.px),
    ) {
        Text(stringResource(R.string.options_title), style = body(38, 800))
        Column(Modifier.fillMaxWidth().card(RoundedCornerShape(20.px), borderWidth = 1.px).padding(22.px), verticalArrangement = Arrangement.spacedBy(6.px)) {
            Text("%03d · %s".format(channel.number, channel.name), style = body(24, 800, Sanga.Accent), maxLines = 1, overflow = TextOverflow.Ellipsis)
            if (now != null) {
                Text(now.title, style = body(22, 700), maxLines = 2, overflow = TextOverflow.Ellipsis)
                Text("${guide.time(now.startMs)}–${guide.time(now.endMs)}" + (now.category?.let { " · $it" } ?: ""), style = body(17, 500, Sanga.Text3))
                now.description?.let { Text(it, style = body(17, 400, Sanga.Text2), maxLines = 3, overflow = TextOverflow.Ellipsis) }
            } else {
                Text(stringResource(R.string.no_epg), style = body(18, 400, Sanga.Text3))
            }
        }
        Column(Modifier.weight(1f), verticalArrangement = Arrangement.spacedBy(8.px)) {
            items.forEachIndexed { i, item ->
                Focusable(onClick = item.action, scaleOnFocus = false, modifier = if (i == 0) Modifier.focusRequester(first) else Modifier) { focused ->
                    Row(
                        Modifier.fillMaxWidth()
                            .background(if (focused) Sanga.Accent else Color.Transparent, RoundedCornerShape(16.px))
                            .padding(horizontal = 20.px, vertical = 15.px),
                        verticalAlignment = Alignment.CenterVertically,
                        horizontalArrangement = Arrangement.spacedBy(18.px),
                    ) {
                        SIcon(item.icon, if (focused) Sanga.OnAccent else if (item.icon == SIcons.Star && isFavorite) Gold else Sanga.Text2, 28.px)
                        Text(item.label, style = body(23, 700, if (focused) Sanga.OnAccent else Sanga.Text), modifier = Modifier.weight(1f), maxLines = 1)
                        item.value?.let {
                            Text(it, style = body(19, 600, if (focused) Sanga.OnAccent else Sanga.Text3), maxLines = 1, overflow = TextOverflow.Ellipsis, modifier = Modifier.widthIn(max = 260.px))
                        }
                    }
                }
            }
        }
        Row(horizontalArrangement = Arrangement.spacedBy(24.px)) {
            KeyHint("OK", stringResource(R.string.hint_select))
            KeyHint("◀", stringResource(R.string.hint_close))
        }
    }
}

// ---------------------------------------------------------------- Programme guide

@Composable
internal fun GuidePanel(
    all: List<ChannelDto>,
    current: ChannelDto?,
    guide: Guide,
    nowMs: Long,
    favorites: List<Long>,
    onPick: (ChannelDto) -> Unit,
) {
    var focusedCh by remember { mutableStateOf(current ?: all.firstOrNull()) }
    val listState = rememberLazyListState()
    val reqs = remember(all) { List(all.size) { FocusRequester() } }
    LaunchedEffect(all) {
        val idx = all.indexOfFirst { it.id == current?.id }.coerceAtLeast(0)
        if (all.isNotEmpty()) {
            listState.scrollToItem((idx - 2).coerceAtLeast(0))
            delay(60)
            runCatching { reqs[idx].requestFocus() }
        }
    }

    Box(Modifier.fillMaxSize().background(Sanga.Deepest.copy(alpha = 0.96f))) {
        Column(Modifier.fillMaxSize().padding(start = 72.px, top = 52.px, end = 72.px, bottom = 36.px), verticalArrangement = Arrangement.spacedBy(24.px)) {
            Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(16.px)) {
                SIcon(SIcons.Calendar, Sanga.Accent, 40.px)
                Text(stringResource(R.string.guide_title), style = body(40, 800), modifier = Modifier.weight(1f))
                Text(guide.time(nowMs), style = body(30, 700, Sanga.Text2))
            }
            Row(Modifier.weight(1f), horizontalArrangement = Arrangement.spacedBy(36.px)) {
                // Channels with what is on now.
                LazyColumn(state = listState, modifier = Modifier.width(720.px).fillMaxHeight(), verticalArrangement = Arrangement.spacedBy(8.px)) {
                    itemsIndexed(all, key = { _, c -> c.id }) { i, ch ->
                        val now = guide.nowNext(ch, nowMs).first
                        Focusable(
                            onClick = { onPick(ch) }, scaleOnFocus = false,
                            modifier = Modifier.focusRequester(reqs[i]).onFocusChanged { if (it.isFocused) focusedCh = ch },
                        ) { focused ->
                            Row(
                                Modifier.fillMaxWidth()
                                    .background(if (focused) Sanga.Accent else if (ch.id == current?.id) Sanga.Card else Color.Transparent, RoundedCornerShape(16.px))
                                    .padding(horizontal = 20.px, vertical = 12.px),
                                verticalAlignment = Alignment.CenterVertically,
                                horizontalArrangement = Arrangement.spacedBy(18.px),
                            ) {
                                Text("%03d".format(ch.number), style = body(22, 800, if (focused) Sanga.OnAccent else Sanga.Text3), modifier = Modifier.widthIn(min = 52.px))
                                Column(Modifier.weight(1f), verticalArrangement = Arrangement.spacedBy(4.px)) {
                                    Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(8.px)) {
                                        Text(ch.name, style = body(22, 700, if (focused) Sanga.OnAccent else Sanga.Text), maxLines = 1, overflow = TextOverflow.Ellipsis, modifier = Modifier.weight(1f, fill = false))
                                        if (ch.id in favorites) SIcon(SIcons.Star, if (focused) Sanga.OnAccent else Gold, 18.px)
                                        if (ch.isAdult) SIcon(SIcons.Lock, if (focused) Sanga.OnAccent else Sanga.Error, 16.px)
                                    }
                                    Text(now?.title ?: "—", style = body(17, 500, if (focused) Sanga.OnAccent.copy(alpha = 0.8f) else Sanga.Text3), maxLines = 1, overflow = TextOverflow.Ellipsis)
                                }
                            }
                        }
                    }
                }
                // Schedule of the focused channel.
                Column(Modifier.weight(1f).fillMaxHeight().card(RoundedCornerShape(24.px), borderWidth = 1.px).padding(28.px), verticalArrangement = Arrangement.spacedBy(14.px)) {
                    val ch = focusedCh
                    if (ch == null) return@Column
                    Text("%03d · %s".format(ch.number, ch.name), style = body(28, 800, Sanga.Accent), maxLines = 1, overflow = TextOverflow.Ellipsis)
                    val upcoming = guide.upcoming(ch, nowMs)
                    if (upcoming.isEmpty()) {
                        Text(stringResource(R.string.no_epg), style = body(22, 500, Sanga.Text3))
                    } else {
                        upcoming.take(9).forEachIndexed { idx, p ->
                            val live = p.isOnAt(nowMs)
                            Row(horizontalArrangement = Arrangement.spacedBy(20.px)) {
                                Text(guide.time(p.startMs), style = body(21, 800, if (live) Sanga.Accent else Sanga.Text2), modifier = Modifier.width(84.px))
                                Column(Modifier.weight(1f), verticalArrangement = Arrangement.spacedBy(4.px)) {
                                    Text(p.title, style = body(21, if (live) 800 else 600, Sanga.Text), maxLines = 1, overflow = TextOverflow.Ellipsis)
                                    if (live) {
                                        ProgressLine(p.progress(nowMs), Sanga.Accent, Sanga.Border, Modifier.widthIn(max = 520.px))
                                        p.description?.let { Text(it, style = body(17, 400, Sanga.Text3), maxLines = 2, overflow = TextOverflow.Ellipsis) }
                                    } else if (idx == 1) {
                                        p.category?.let { Text(it, style = body(16, 400, Sanga.Text3), maxLines = 1) }
                                    }
                                }
                            }
                        }
                    }
                }
            }
            Row(horizontalArrangement = Arrangement.spacedBy(24.px)) {
                KeyHint("▲ ▼", stringResource(R.string.hint_select))
                KeyHint("OK", stringResource(R.string.guide_hint_watch))
                KeyHint("BACK", stringResource(R.string.hint_close))
            }
        }
    }
}

// ---------------------------------------------------------------- Adult PIN

@Composable
internal fun AdultPinDialog(channel: ChannelDto, pinHash: PinHash?, onUnlocked: () -> Unit, modifier: Modifier = Modifier) {
    val context = LocalContext.current
    var pin by remember { mutableStateOf("") }
    var msg by remember { mutableStateOf<String?>(null) }
    var busy by remember { mutableStateOf(false) }

    suspend fun check() {
        busy = true
        val r = withContext(Dispatchers.Default) { AdultLock.verify(pin, pinHash) } // PBKDF2 takes ~0.3 s on TVs
        busy = false
        when (r) {
            AdultLock.Result.Ok -> onUnlocked()
            is AdultLock.Result.Wrong -> { pin = ""; msg = context.getString(R.string.adult_wrong, r.remaining) }
            AdultLock.Result.LockedOut -> { pin = ""; msg = context.getString(R.string.adult_locked) }
        }
    }

    LaunchedEffect(pin) { if (pin.length == 4 && !busy) check() }

    fun press(k: String) {
        if (busy) return
        when (k) {
            "DEL" -> pin = pin.dropLast(1)
            else -> if (pin.length < 4) { pin += k; msg = null }
        }
    }

    val first = remember { FocusRequester() }
    LaunchedEffect(Unit) { delay(60); runCatching { first.requestFocus() } }

    Box(Modifier.fillMaxSize().background(Sanga.Deepest.copy(alpha = 0.7f)), contentAlignment = Alignment.Center) {
        Column(
            modifier.width(560.px).card(RoundedCornerShape(30.px), borderWidth = 1.px).padding(44.px)
                .onPreviewKeyEvent { e ->
                    val code = e.key.nativeKeyCode
                    if (e.type == KeyEventType.KeyDown && code in android.view.KeyEvent.KEYCODE_0..android.view.KeyEvent.KEYCODE_9) {
                        press((code - android.view.KeyEvent.KEYCODE_0).toString()); true
                    } else false
                },
            horizontalAlignment = Alignment.CenterHorizontally,
            verticalArrangement = Arrangement.spacedBy(22.px),
        ) {
            Box(Modifier.size(80.px).background(Sanga.Surface2, RoundedCornerShape(24.px)), contentAlignment = Alignment.Center) {
                SIcon(SIcons.Lock, Sanga.Error, 40.px)
            }
            Text(stringResource(R.string.adult_title), style = body(34, 800))
            Text("%03d · %s".format(channel.number, channel.name), style = body(22, 600, Sanga.Accent))
            Text(stringResource(R.string.adult_desc), style = body(20, 400, Sanga.Text3), textAlign = TextAlign.Center)
            Row(horizontalArrangement = Arrangement.spacedBy(20.px)) {
                repeat(4) { i ->
                    Box(Modifier.size(24.px).border(2.px, Color(0xFF6F94B3), CircleShape).background(if (i < pin.length) Sanga.Accent else Color.Transparent, CircleShape))
                }
            }
            val rows = listOf(listOf("1", "2", "3"), listOf("4", "5", "6"), listOf("7", "8", "9"), listOf("", "0", "DEL"))
            Column(verticalArrangement = Arrangement.spacedBy(12.px)) {
                rows.forEachIndexed { r, row ->
                    Row(horizontalArrangement = Arrangement.spacedBy(12.px)) {
                        row.forEachIndexed { c, label ->
                            if (label.isEmpty()) {
                                Box(Modifier.weight(1f))
                            } else {
                                Focusable(onClick = { press(label) }, modifier = Modifier.weight(1f).then(if (r == 0 && c == 0) Modifier.focusRequester(first) else Modifier)) { focused ->
                                    Box(
                                        Modifier.fillMaxWidth().height(72.px)
                                            .background(if (focused) Sanga.Border2 else Sanga.Surface2, RoundedCornerShape(18.px))
                                            .border(if (focused) 3.px else 1.px, if (focused) Sanga.Accent else Color(0xFF1F4A70), RoundedCornerShape(18.px)),
                                        contentAlignment = Alignment.Center,
                                    ) { Text(if (label == "DEL") "⌫" else label, style = body(30, 700)) }
                                }
                            }
                        }
                    }
                }
            }
            Text(msg ?: "", style = body(19, 600, Sanga.Error), textAlign = TextAlign.Center, modifier = Modifier.heightIn(min = 28.px))
        }
    }
}
