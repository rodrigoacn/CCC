package com.classexpress.app

/**
 * Configuración de la app.
 *
 * BASE_URL apunta a `api_mobile.php` de la web (ClassExpress).
 *  - Emulador Android -> 10.0.2.2 es el `localhost` del PC (XAMPP).
 *  - Dispositivo físico -> reemplaza por la IP LAN de tu PC, ej. "http://192.168.1.50/CCC/".
 *  - Producción -> "https://classexpress.online/CCC/".
 */
object Config {
    const val BASE_URL = "http://161.22.47.181/CCC/"

    /** Minutos gratis antes de empezar a cobrar (espejo de la web). */
    const val FREE_MINUTES = 3

    /** Colores por materia (id -> color), iguales a la web. */
    val SUBJECT_COLORS = mapOf(
        1 to "#2563EB",
        2 to "#059669",
        3 to "#7C3AED",
        4 to "#0284C7",
        5 to "#D97706",
        6 to "#0D9488",
        7 to "#DC2626",
        8 to "#DB2777",
        9 to "#EA580C",
        10 to "#0891B2",
        11 to "#E11D48",
    )

    fun subjectColor(id: Int): Long = SUBJECT_COLORS[id]?.removePrefix("#")?.toLong(16) ?: 0x66ddbdL
}
