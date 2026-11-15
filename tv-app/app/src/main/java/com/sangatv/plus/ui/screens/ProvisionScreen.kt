package com.sangatv.plus.ui.screens

import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.heightIn
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.text.BasicTextField
import androidx.compose.foundation.text.KeyboardActions
import androidx.compose.foundation.text.KeyboardOptions
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.focus.FocusDirection
import androidx.compose.ui.focus.FocusRequester
import androidx.compose.ui.input.key.Key
import androidx.compose.ui.input.key.KeyEventType
import androidx.compose.ui.input.key.key
import androidx.compose.ui.input.key.onPreviewKeyEvent
import androidx.compose.ui.input.key.type
import androidx.compose.ui.platform.LocalFocusManager
import androidx.compose.ui.focus.focusRequester
import androidx.compose.ui.focus.onFocusChanged
import androidx.compose.ui.graphics.SolidColor
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.text.input.ImeAction
import androidx.compose.ui.text.input.KeyboardCapitalization
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.em
import androidx.tv.material3.Text
import com.sangatv.plus.R
import com.sangatv.plus.ui.ProvisionState
import com.sangatv.plus.ui.components.Focusable
import com.sangatv.plus.ui.components.SIcon
import com.sangatv.plus.ui.components.SIcons
import com.sangatv.plus.ui.components.card
import com.sangatv.plus.ui.theme.Sanga
import com.sangatv.plus.ui.theme.body
import com.sangatv.plus.ui.theme.display
import com.sangatv.plus.ui.theme.px

@Composable
fun ProvisionScreen(state: ProvisionState, deviceUid: String, server: String, onSubmit: (String) -> Unit, onSkip: (() -> Unit)? = null) {
    val focusManager = LocalFocusManager.current
    var code by remember { mutableStateOf("") }
    var fieldFocused by remember { mutableStateOf(false) }
    val field = remember { FocusRequester() }
    LaunchedEffect(Unit) { runCatching { field.requestFocus() } }

    Box(Modifier.fillMaxSize().background(Sanga.Background), contentAlignment = Alignment.Center) {
        Column(
            Modifier.width(760.px).card(RoundedCornerShape(32.px), borderWidth = 1.px).padding(56.px),
            horizontalAlignment = Alignment.CenterHorizontally,
            verticalArrangement = Arrangement.spacedBy(28.px),
        ) {
            Text("SANGATV PLUS", style = display(26, 0.18f, Sanga.Accent))
            Box(Modifier.size(96.px).background(Sanga.Surface2, RoundedCornerShape(28.px)), contentAlignment = Alignment.Center) {
                SIcon(SIcons.Tv, Sanga.Accent, 48.px)
            }
            Text(stringResource(R.string.provision_title), style = body(44, 800))
            Text(stringResource(R.string.provision_subtitle), style = body(22, 400, Sanga.Text3), textAlign = TextAlign.Center)

            BasicTextField(
                value = code,
                onValueChange = { v -> code = v.uppercase().filter { it.isLetterOrDigit() || it == '-' }.take(9) },
                singleLine = true,
                textStyle = body(48, 800).copy(letterSpacing = 0.2.em, textAlign = TextAlign.Center),
                cursorBrush = SolidColor(Sanga.Accent),
                keyboardOptions = KeyboardOptions(capitalization = KeyboardCapitalization.Characters, autoCorrectEnabled = false, imeAction = ImeAction.Done),
                keyboardActions = KeyboardActions(onDone = { onSubmit(code) }),
                modifier = Modifier
                    .fillMaxWidth()
                    .focusRequester(field)
                    // A text field keeps D-pad focus by default: let ▼ reach "Daftarkan" / "Gunakan TV tanpa …".
                    .onPreviewKeyEvent { e ->
                        if (e.type == KeyEventType.KeyDown && e.key == Key.DirectionDown) {
                            focusManager.moveFocus(FocusDirection.Down)
                        } else {
                            false
                        }
                    }
                    .onFocusChanged { fieldFocused = it.isFocused }
                    .background(Sanga.Surface2, RoundedCornerShape(20.px))
                    .border(if (fieldFocused) 3.px else 1.px, if (fieldFocused) Sanga.Accent else Sanga.Border, RoundedCornerShape(20.px))
                    .padding(vertical = 24.px),
                decorationBox = { inner ->
                    Box(contentAlignment = Alignment.Center) {
                        if (code.isEmpty()) Text("XXXX-XXXX", style = body(48, 800, Sanga.Border2).copy(letterSpacing = 0.2.em))
                        inner()
                    }
                },
            )

            Focusable(onClick = { onSubmit(code) }, modifier = Modifier.fillMaxWidth()) { focused ->
                Text(
                    if (state is ProvisionState.Busy) stringResource(R.string.loading) else stringResource(R.string.provision_submit),
                    style = body(26, 800, Sanga.OnAccent),
                    textAlign = TextAlign.Center,
                    modifier = Modifier.fillMaxWidth()
                        .background(if (focused) Sanga.Accent else Sanga.Accent.copy(alpha = 0.75f), RoundedCornerShape(20.px))
                        .padding(vertical = 22.px),
                )
            }

            // Never trap a TV on this screen: the TV stays usable before registration.
            if (onSkip != null) {
                Focusable(onClick = onSkip, scaleOnFocus = false) { focused ->
                    Text(
                        stringResource(R.string.use_without),
                        style = body(20, 600, if (focused) Sanga.Accent else Sanga.Text3),
                        modifier = Modifier.padding(vertical = 6.px, horizontal = 12.px),
                    )
                }
            }

            Text(
                (state as? ProvisionState.Error)?.let { stringResource(R.string.provision_failed, it.message) } ?: "",
                style = body(20, 600, Sanga.Error), modifier = Modifier.heightIn(min = 30.px),
            )
            Text("ID: $deviceUid\nServer: $server", style = body(16, 400, Sanga.Text3), textAlign = TextAlign.Center)
        }
    }
}
