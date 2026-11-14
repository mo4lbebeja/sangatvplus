package com.sangatv.plus.ui.theme

import androidx.compose.runtime.Composable
import androidx.compose.runtime.CompositionLocalProvider
import androidx.compose.runtime.ReadOnlyComposable
import androidx.compose.runtime.staticCompositionLocalOf
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.platform.LocalConfiguration
import androidx.compose.ui.platform.LocalDensity
import androidx.compose.ui.text.ExperimentalTextApi
import androidx.compose.ui.text.TextStyle
import androidx.compose.ui.text.font.Font
import androidx.compose.ui.text.font.FontFamily
import androidx.compose.ui.text.font.FontVariation
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.Dp
import androidx.compose.ui.unit.TextUnit
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.em
import androidx.tv.material3.MaterialTheme
import androidx.tv.material3.darkColorScheme
import com.sangatv.plus.R

/** "Biru pantai" palette (doc §7). */
object Sanga {
    val Deepest = Color(0xFF031220)
    val Background = Color(0xFF061A2B)
    val Panel = Color(0xFF08223A)
    val Card = Color(0xFF0C2B45)
    val Surface2 = Color(0xFF123756)
    val Border = Color(0xFF1A4366)
    val Border2 = Color(0xFF2C5A80)
    val Accent = Color(0xFF38C6D9)
    val OnAccent = Color(0xFF04232D)
    val NavyActive = Color(0xFF0B3D66)
    val BarLight = Color(0xFFF2F8FB)
    val BarText = Color(0xFF0B2C47)
    val BarMuted = Color(0xFF3E5F7A)
    val Text = Color(0xFFF2F8FB)
    val Text2 = Color(0xFFCFE0EC)
    val Text3 = Color(0xFF9DB9CF)
    val Error = Color(0xFFF08A7E)
}

val Michroma = FontFamily(Font(R.font.michroma))

@OptIn(ExperimentalTextApi::class)
private fun jakarta(weight: Int) = Font(
    R.font.plus_jakarta_sans,
    weight = FontWeight(weight),
    variationSettings = FontVariation.Settings(FontVariation.weight(weight)),
)

val Jakarta = FontFamily(jakarta(300), jakarta(400), jakarta(500), jakarta(600), jakarta(700), jakarta(800))

/**
 * Designs are drawn at 1920×1080 px. LocalScale converts design px to dp for the
 * actual screen, so layouts match the mockup on any TV density.
 */
val LocalScale = staticCompositionLocalOf { 0.5f }

val Int.px: Dp
    @Composable @ReadOnlyComposable get() = (this * LocalScale.current).dp

val Int.tx: TextUnit
    @Composable @ReadOnlyComposable get() = with(LocalDensity.current) { (this@tx * LocalScale.current).dp.toSp() }

@Composable
fun SangaTheme(content: @Composable () -> Unit) {
    val scale = LocalConfiguration.current.screenWidthDp / 1920f
    CompositionLocalProvider(LocalScale provides scale) {
        MaterialTheme(
            colorScheme = darkColorScheme(
                primary = Sanga.Accent,
                onPrimary = Sanga.OnAccent,
                background = Sanga.Background,
                surface = Sanga.Card,
                onSurface = Sanga.Text,
                error = Sanga.Error,
            ),
            content = content,
        )
    }
}

@Composable
@ReadOnlyComposable
fun body(size: Int, weight: Int = 400, color: Color = Sanga.Text) =
    TextStyle(fontFamily = Jakarta, fontWeight = FontWeight(weight), fontSize = size.tx, color = color)

@Composable
@ReadOnlyComposable
fun display(size: Int, spacing: Float = 0.1f, color: Color = Sanga.Text) =
    TextStyle(fontFamily = Michroma, fontSize = size.tx, letterSpacing = spacing.em, color = color)
