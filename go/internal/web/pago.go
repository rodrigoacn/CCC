package web

import (
	"fmt"
	"strings"
)

// formatNumber mirrors number_format($v, $dec, '.', ',').
func formatNumber(v float64, dec int) string {
	s := fmt.Sprintf("%."+fmt.Sprint(dec)+"f", v)
	neg := strings.HasPrefix(s, "-")
	if neg {
		s = s[1:]
	}
	parts := strings.SplitN(s, ".", 2)
	intPart := parts[0]
	var b strings.Builder
	n := len(intPart)
	for i := 0; i < n; i++ {
		if i > 0 && (n-i)%3 == 0 {
			b.WriteByte(',')
		}
		b.WriteByte(intPart[i])
	}
	out := b.String()
	if len(parts) == 2 {
		out += "." + parts[1]
	}
	if neg {
		return "-" + out
	}
	return out
}
