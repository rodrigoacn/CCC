package com.classexpress.app.ui.theme

import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.lightColorScheme
import androidx.compose.runtime.Composable
import androidx.compose.ui.graphics.Color

private val LightColors = lightColorScheme(
    primary = Mint,
    onPrimary = Color.White,
    primaryContainer = MintDark,
    onPrimaryContainer = Color.White,
    secondary = Ink,
    onSecondary = Color.White,
    background = PageBackground,
    onBackground = Ink,
    surface = CardBackground,
    onSurface = Ink,
    surfaceVariant = PageBackground,
    onSurfaceVariant = InkSecondary,
    error = Danger,
    onError = Color.White,
    outline = Hairline,
)

@Composable
fun ClassExpressTheme(content: @Composable () -> Unit) {
    MaterialTheme(
        colorScheme = LightColors,
        typography = Typography,
        content = content,
    )
}
