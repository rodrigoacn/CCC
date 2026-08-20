package com.classexpress.app.ui.components

import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.ErrorOutline
import androidx.compose.material.icons.filled.Search
import androidx.compose.material.icons.filled.Star
import androidx.compose.material3.Button
import androidx.compose.material3.ButtonDefaults
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.Icon
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.DisposableEffect
import androidx.compose.runtime.remember
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.vector.ImageVector
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.compose.ui.viewinterop.AndroidView
import coil.compose.AsyncImage
import com.classexpress.app.ui.theme.Danger
import com.classexpress.app.ui.theme.InkSecondary
import com.classexpress.app.ui.theme.Mint
import com.classexpress.app.ui.theme.Star
import com.google.android.gms.ads.AdRequest
import com.google.android.gms.ads.AdSize
import com.google.android.gms.ads.AdView

fun parseColorHex(hex: String?): Color {
    if (hex == null) return Mint
    return try {
        Color(android.graphics.Color.parseColor(hex))
    } catch (_: Exception) {
        Mint
    }
}

fun formatRating(rating: Double): String {
    val r = (rating * 10).toInt() / 10.0
    return if (r % 1.0 == 0.0) r.toInt().toString() else r.toString()
}

@Composable
fun LoadingView(modifier: Modifier = Modifier) {
    Box(modifier.fillMaxWidth().padding(48.dp), contentAlignment = Alignment.Center) {
        CircularProgressIndicator(color = Mint)
    }
}

@Composable
fun ErrorView(message: String, modifier: Modifier = Modifier, onRetry: (() -> Unit)? = null) {
    Column(
        modifier.padding(32.dp),
        horizontalAlignment = Alignment.CenterHorizontally,
        verticalArrangement = Arrangement.spacedBy(12.dp),
    ) {
        Icon(Icons.Filled.ErrorOutline, null, tint = Danger, modifier = Modifier.size(40.dp))
        Text(message, textAlign = TextAlign.Center, color = InkSecondary)
        if (onRetry != null) {
            Button(onClick = onRetry, colors = ButtonDefaults.buttonColors(containerColor = Mint)) {
                Text("Reintentar")
            }
        }
    }
}

@Composable
fun EmptyView(icon: ImageVector, text: String, modifier: Modifier = Modifier) {
    Column(
        modifier.padding(vertical = 48.dp),
        horizontalAlignment = Alignment.CenterHorizontally,
        verticalArrangement = Arrangement.spacedBy(10.dp),
    ) {
        Icon(icon, null, tint = InkSecondary, modifier = Modifier.size(40.dp))
        Text(text, color = InkSecondary, fontSize = 16.sp, textAlign = TextAlign.Center)
    }
}

@Composable
fun SectionHeader(text: String, modifier: Modifier = Modifier, tint: Color = InkSecondary) {
    Text(
        text.uppercase(),
        modifier = modifier.padding(top = 20.dp, bottom = 10.dp),
        color = tint,
        fontSize = 11.sp,
        fontWeight = FontWeight.Bold,
        letterSpacing = 1.sp,
    )
}

@Composable
fun RatingStars(rating: Double, size: Int = 12) {
    Row(verticalAlignment = Alignment.CenterVertically) {
        val full = rating.toInt()
        for (i in 1..5) {
            Icon(
                Icons.Filled.Star,
                contentDescription = null,
                tint = if (i <= full) Star else Color(0xFF999999),
                modifier = Modifier.size(size.dp),
            )
        }
    }
}

@Composable
fun PrimaryButton(
    text: String,
    onClick: () -> Unit,
    modifier: Modifier = Modifier,
    enabled: Boolean = true,
    loading: Boolean = false,
) {
    Button(
        onClick = onClick,
        modifier = modifier.fillMaxWidth().height(52.dp),
        enabled = enabled,
        colors = ButtonDefaults.buttonColors(
            containerColor = Mint,
            contentColor = Color.White,
            disabledContainerColor = Color(0xFFCCE8DF),
            disabledContentColor = Color.White,
        ),
        shape = RoundedCornerShape(14.dp),
    ) {
        if (loading) {
            CircularProgressIndicator(color = Color.White, modifier = Modifier.size(22.dp), strokeWidth = 2.dp)
        } else {
            Text(text, fontSize = 16.sp, fontWeight = FontWeight.Bold)
        }
    }
}

@Composable
fun Chip(
    text: String,
    selected: Boolean,
    onClick: () -> Unit,
    modifier: Modifier = Modifier,
    tint: Color = Mint,
) {
    val bg = if (selected) tint else tint.copy(alpha = 0.13f)
    val fg = if (selected) Color.White else tint
    Row(
        modifier = modifier
            .clip(RoundedCornerShape(20.dp))
            .background(bg)
            .border(1.dp, tint, RoundedCornerShape(20.dp))
            .padding(horizontal = 14.dp, vertical = 8.dp),
        verticalAlignment = Alignment.CenterVertically,
    ) {
        Text(text, color = fg, fontSize = 12.sp, fontWeight = FontWeight.Medium)
    }
}

@Composable
fun Avatar(
    avatarUrl: String?,
    name: String,
    modifier: Modifier = Modifier,
    size: Int = 80,
) {
    val shape = CircleShape
    Box(
        modifier
            .size(size.dp)
            .clip(shape)
            .background(Mint.copy(alpha = 0.15f)),
        contentAlignment = Alignment.Center,
    ) {
        if (!avatarUrl.isNullOrBlank()) {
            AsyncImage(
                model = avatarUrl,
                contentDescription = null,
                modifier = Modifier.size(size.dp).clip(shape),
            )
        } else {
            Text(
                name.trim().firstOrNull()?.uppercase() ?: "?",
                color = Mint,
                fontSize = (size * 0.4).sp,
                fontWeight = FontWeight.Bold,
            )
        }
    }
}

@Composable
fun LiveBadge() {
    Row(
        modifier = Modifier
            .clip(RoundedCornerShape(20.dp))
            .background(Danger)
            .padding(horizontal = 8.dp, vertical = 3.dp),
        verticalAlignment = Alignment.CenterVertically,
    ) {
        Box(Modifier.size(6.dp).clip(CircleShape).background(Color.White))
        Spacer(Modifier.width(4.dp))
        Text("EN VIVO", color = Color.White, fontSize = 10.sp, fontWeight = FontWeight.Bold, letterSpacing = 1.sp)
    }
}

@Composable
fun FriendLabel() {
    Text(
        "· Amigo",
        color = Mint,
        fontSize = 10.sp,
        fontWeight = FontWeight.SemiBold,
    )
}

@Composable
fun BannerAd(modifier: Modifier = Modifier) {
    val adUnitId = "ca-app-pub-5524033374028556/5118081375"
    val context = LocalContext.current
    val adView = remember {
        AdView(context).apply {
            setAdSize(AdSize.BANNER)
            setAdUnitId(adUnitId)
        }
    }
    DisposableEffect(Unit) {
        val request = AdRequest.Builder().build()
        adView.loadAd(request)
        onDispose { adView.destroy() }
    }
    Box(
        modifier = modifier.fillMaxWidth().background(Color.White).padding(vertical = 6.dp),
        contentAlignment = Alignment.Center,
    ) {
        AndroidView(factory = { adView })
    }
}
