package com.sangatv.plus.ui.components

import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.SolidColor
import androidx.compose.ui.graphics.StrokeCap
import androidx.compose.ui.graphics.StrokeJoin
import androidx.compose.ui.graphics.vector.ImageVector
import androidx.compose.ui.graphics.vector.PathParser
import androidx.compose.ui.unit.dp

/** Stroke icons from the mockup (24×24 viewBox, 1.6 stroke). Tint with ColorFilter. */
object SIcons {
    private fun icon(name: String, stroke: Float = 1.6f, vararg paths: String): ImageVector =
        ImageVector.Builder(name, 24.dp, 24.dp, 24f, 24f).apply {
            paths.forEach {
                addPath(
                    pathData = PathParser().parsePathString(it).toNodes(),
                    stroke = SolidColor(Color.White),
                    strokeLineWidth = stroke,
                    strokeLineCap = StrokeCap.Round,
                    strokeLineJoin = StrokeJoin.Round,
                )
            }
        }.build()

    private fun circle(cx: Float, cy: Float, r: Float) = "M${cx - r} ${cy}a$r $r 0 1 0 ${2 * r} 0a$r $r 0 1 0 ${-2 * r} 0"

    val Home = icon("home", 1.6f, "M3 11l9-7 9 7", "M5 10v10h14V10", "M10 20v-5h4v5")
    val Tv = icon("tv", 1.6f, "M4 5h16a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V7a2 2 0 0 1 2-2z", "M8 21h8M12 18v3M9 2l3 3 3-3")
    val Info = icon("info", 1.6f, circle(12f, 12f, 9f), "M12 16v-5M12 8h.01")
    val RoomService = icon("roomservice", 1.6f, "M4 16h16", "M6 16a6 6 0 0 1 12 0", "M12 8V6M3 19h18")
    val Facilities = icon("facilities", 1.6f, "M2 19c2 0 2-1.5 4-1.5s2 1.5 4 1.5 2-1.5 4-1.5 2 1.5 4 1.5 2-1.5 4-1.5", "M7 15V6a2 2 0 0 1 4 0M13 15V6a2 2 0 0 1 4 0M7 9h6M7 12h6")
    val Apps = icon("apps", 1.6f, "M4.5 3h4a1.5 1.5 0 0 1 1.5 1.5v4a1.5 1.5 0 0 1-1.5 1.5h-4A1.5 1.5 0 0 1 3 8.5v-4A1.5 1.5 0 0 1 4.5 3z", "M15.5 3h4A1.5 1.5 0 0 1 21 4.5v4a1.5 1.5 0 0 1-1.5 1.5h-4A1.5 1.5 0 0 1 14 8.5v-4A1.5 1.5 0 0 1 15.5 3z", "M4.5 14h4a1.5 1.5 0 0 1 1.5 1.5v4A1.5 1.5 0 0 1 8.5 21h-4A1.5 1.5 0 0 1 3 19.5v-4A1.5 1.5 0 0 1 4.5 14z", "M15.5 14h4a1.5 1.5 0 0 1 1.5 1.5v4a1.5 1.5 0 0 1-1.5 1.5h-4a1.5 1.5 0 0 1-1.5-1.5v-4a1.5 1.5 0 0 1 1.5-1.5z")
    val ChevronRight = icon("chevright", 2.2f, "M9 6l6 6-6 6")
    val ChevronLeft = icon("chevleft", 2.2f, "M15 18l-6-6 6-6")
    val Clock = icon("clock", 1.6f, circle(12f, 12f, 9f), "M12 7v5l3 2")
    val Weather = icon("weather", 1.6f, circle(9f, 9f, 3.5f), "M9 2.5v1.5M3.5 9H2M4.8 4.8l1 1M13.2 4.8l-1 1", "M8 20h9a4 4 0 0 0 0-8 5 5 0 0 0-9.6 1.5A3.3 3.3 0 0 0 8 20z")
    val Lock = icon("lock", 2f, "M6 11h12a2 2 0 0 1 2 2v6a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2v-6a2 2 0 0 1 2-2z", "M8 11V7a4 4 0 0 1 8 0v4")
    val Offline = icon("offline", 1.8f, "M2 2l20 20", "M8.5 16.5a5 5 0 0 1 7 0", "M5 12.9a10 10 0 0 1 5.2-2.8", "M19 12.9a10 10 0 0 0-2-1.4", "M12 20h.01")
}
