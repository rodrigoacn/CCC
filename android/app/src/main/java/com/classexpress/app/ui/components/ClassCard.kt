package com.classexpress.app.ui.components

import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.Book
import androidx.compose.material.icons.filled.Schedule
import androidx.compose.material.icons.filled.Group
import androidx.compose.material3.Icon
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.classexpress.app.model.ClassItem
import com.classexpress.app.ui.theme.Hairline
import com.classexpress.app.ui.theme.Ink
import com.classexpress.app.ui.theme.InkSecondary
import com.classexpress.app.ui.theme.Mint
import com.classexpress.app.ui.theme.Success

/**
 * Tarjeta de clase (mismo diseño que contenido.php y buscar.php).
 * `showMeta = true` muestra chips (materia/duración/alumnos) + estrellas, como en Buscar.
 */
@Composable
fun ClassCard(
    item: ClassItem,
    onClick: () -> Unit,
    modifier: Modifier = Modifier,
    showMeta: Boolean = false,
) {
    val borderColor = when {
        item.isLive -> Success
        item.isFriend -> Mint
        else -> Hairline
    }

    Column(
        modifier
            .fillMaxWidth()
            .clip(RoundedCornerShape(16.dp))
            .background(Color.White)
            .border(1.dp, borderColor, RoundedCornerShape(16.dp))
            .clickable { onClick() }
            .padding(16.dp),
        verticalArrangement = Arrangement.spacedBy(6.dp),
    ) {
        Row(verticalAlignment = Alignment.CenterVertically) {
            Column(Modifier.weight(1f), verticalArrangement = Arrangement.spacedBy(2.dp)) {
                Row(verticalAlignment = Alignment.CenterVertically) {
                    Text(
                        item.titulo,
                        fontSize = 15.sp,
                        fontWeight = FontWeight.SemiBold,
                        color = if (item.isLive) Ink else Mint,
                        lineHeight = 20.sp,
                    )
                    if (item.isLive) {
                        Spacer(Modifier.width(8.dp))
                        LiveBadge()
                    }
                    if (item.isFriend) {
                        Spacer(Modifier.width(6.dp))
                        FriendLabel()
                    }
                }
                Text(item.profesor, fontSize = 13.sp, color = InkSecondary)
            }
        }

        if (showMeta) {
            Row(horizontalArrangement = Arrangement.spacedBy(6.dp)) {
                MetaChip(Icons.Filled.Book, item.materia)
                if ((item.duracionMinutos ?: 0) > 0) {
                    MetaChip(Icons.Filled.Schedule, "${item.duracionMinutos}min")
                }
                if ((item.alumnosMax ?: 0) > 0) {
                    MetaChip(Icons.Filled.Group, "${item.alumnosActivos ?: 0}/${item.alumnosMax}")
                }
            }
        } else {
            item.descripcion?.takeIf { it.isNotBlank() }?.let { desc ->
                val truncated = if (desc.length > 120) desc.take(120) + "..." else desc
                Text(truncated, fontSize = 12.sp, color = InkSecondary, lineHeight = 16.sp)
            }
        }

        Row(
            Modifier.fillMaxWidth(),
            horizontalArrangement = Arrangement.SpaceBetween,
            verticalAlignment = Alignment.CenterVertically,
        ) {
            Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(4.dp)) {
                RatingStars(item.displayRating, size = 11)
                Text(
                    formatRating(item.displayRating),
                    fontSize = 11.sp,
                    fontWeight = FontWeight.SemiBold,
                    color = InkSecondary,
                )
            }
            Row(verticalAlignment = Alignment.Bottom) {
                Text(
                    item.precio.toInt().toString(),
                    fontSize = 22.sp,
                    fontWeight = FontWeight.Bold,
                    color = Mint,
                )
                Spacer(Modifier.width(2.dp))
                Text("cr.", fontSize = 12.sp, color = InkSecondary, modifier = Modifier.padding(bottom = 3.dp))
            }
        }
    }
}

@Composable
private fun MetaChip(icon: androidx.compose.ui.graphics.vector.ImageVector, text: String) {
    Row(
        Modifier
            .clip(RoundedCornerShape(8.dp))
            .background(Color(0xFFF1F5F9))
            .padding(horizontal = 8.dp, vertical = 4.dp),
        verticalAlignment = Alignment.CenterVertically,
    ) {
        Icon(icon, contentDescription = null, tint = InkSecondary, modifier = Modifier.size(12.dp))
        Spacer(Modifier.width(4.dp))
        Text(text, fontSize = 11.sp, color = InkSecondary, fontWeight = FontWeight.Medium)
    }
}
