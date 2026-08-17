package store

import (
	"context"
	"database/sql"
	"errors"
	"strconv"
	"time"
)

// DB provides the generic query helpers equivalent to db.php:
// dbAll, dbOne and dbExec, returning rows as map[string]any with
// PHP/PDO-like value types (all scalars as strings, NULL as nil,
// DATETIME as "2006-01-02 15:04:05").
type DB struct {
	Pool *sql.DB
}

// QueryAll mirrors dbAll(): every row as a map[string]any.
func (d *DB) QueryAll(ctx context.Context, query string, args ...any) ([]map[string]any, error) {
	rows, err := d.Pool.QueryContext(ctx, query, args...)
	if err != nil {
		return nil, err
	}
	defer rows.Close()

	cols, err := rows.Columns()
	if err != nil {
		return nil, err
	}
	out := []map[string]any{}
	for rows.Next() {
		vals := make([]any, len(cols))
		ptrs := make([]any, len(cols))
		for i := range vals {
			ptrs[i] = &vals[i]
		}
		if err := rows.Scan(ptrs...); err != nil {
			return nil, err
		}
		row := make(map[string]any, len(cols))
		for i, c := range cols {
			row[c] = normalize(vals[i])
		}
		out = append(out, row)
	}
	return out, rows.Err()
}

// QueryOne mirrors dbOne(): returns nil when no rows are found.
func (d *DB) QueryOne(ctx context.Context, query string, args ...any) (map[string]any, error) {
	rows, err := d.Pool.QueryContext(ctx, query, args...)
	if err != nil {
		return nil, err
	}
	defer rows.Close()

	cols, err := rows.Columns()
	if err != nil {
		return nil, err
	}
	if !rows.Next() {
		return nil, rows.Err()
	}
	vals := make([]any, len(cols))
	ptrs := make([]any, len(cols))
	for i := range vals {
		ptrs[i] = &vals[i]
	}
	if err := rows.Scan(ptrs...); err != nil {
		return nil, err
	}
	row := make(map[string]any, len(cols))
	for i, c := range cols {
		row[c] = normalize(vals[i])
	}
	return row, rows.Err()
}

// Exec mirrors dbExec(): returns last insert id when available,
// otherwise the number of affected rows.
func (d *DB) Exec(ctx context.Context, query string, args ...any) (int64, error) {
	res, err := d.Pool.ExecContext(ctx, query, args...)
	if err != nil {
		return 0, err
	}
	if id, err := res.LastInsertId(); err == nil && id != 0 {
		return id, nil
	}
	return res.RowsAffected()
}

// Begin starts a transaction.
func (d *DB) Begin(ctx context.Context) (*sql.Tx, error) {
	return d.Pool.BeginTx(ctx, nil)
}

func normalize(v any) any {
	switch t := v.(type) {
	case nil:
		return nil
	case []byte:
		return string(t)
	case string:
		return t
	case int64:
		return strconv.FormatInt(t, 10)
	case float64:
		return strconv.FormatFloat(t, 'f', -1, 64)
	case time.Time:
		return t.Format("2006-01-02 15:04:05")
	case bool:
		if t {
			return "1"
		}
		return "0"
	default:
		return stringify(t)
	}
}

func stringify(v any) any {
	switch t := v.(type) {
	case int:
		return strconv.Itoa(t)
	case int32:
		return strconv.FormatInt(int64(t), 10)
	case float32:
		return strconv.FormatFloat(float64(t), 'f', -1, 32)
	default:
		return t
	}
}

// ---------------------------------------------------------------------------
// Explicit casts (mirror PHP's (int), (float), (bool), (string) and ?? defaults)
// ---------------------------------------------------------------------------

// Str returns the PHP-equivalent string cast. NULL becomes "".
func Str(v any) string {
	switch t := v.(type) {
	case nil:
		return ""
	case string:
		return t
	case []byte:
		return string(t)
	case int:
		return strconv.Itoa(t)
	case int64:
		return strconv.FormatInt(t, 10)
	case float64:
		return strconv.FormatFloat(t, 'f', -1, 64)
	case bool:
		if t {
			return "1"
		}
		return "0"
	default:
		return ""
	}
}

// Int mirrors PHP (int) cast.
func Int(v any) int64 {
	switch t := v.(type) {
	case nil:
		return 0
	case int:
		return int64(t)
	case int64:
		return t
	case float64:
		return int64(t)
	case string:
		f, err := strconv.ParseFloat(t, 64)
		if err != nil {
			return 0
		}
		return int64(f)
	case []byte:
		return Int(string(t))
	case bool:
		if t {
			return 1
		}
		return 0
	default:
		return 0
	}
}

// Float mirrors PHP (float) cast.
func Float(v any) float64 {
	switch t := v.(type) {
	case nil:
		return 0
	case int:
		return float64(t)
	case int64:
		return float64(t)
	case float64:
		return t
	case string:
		f, err := strconv.ParseFloat(t, 64)
		if err != nil {
			return 0
		}
		return f
	case []byte:
		return Float(string(t))
	case bool:
		if t {
			return 1
		}
		return 0
	default:
		return 0
	}
}

// Bool mirrors PHP (bool) cast.
func Bool(v any) bool {
	switch t := v.(type) {
	case nil:
		return false
	case bool:
		return t
	case int:
		return t != 0
	case int64:
		return t != 0
	case float64:
		return t != 0
	case string:
		return t != "" && t != "0"
	case []byte:
		return Bool(string(t))
	default:
		return false
	}
}

// Coalesce mirrors PHP $a ?? $b.
func Coalesce(v, fallback any) any {
	if v == nil {
		return fallback
	}
	return v
}

// Now returns the PHP date('Y-m-d H:i:s') string.
func Now() string {
	return time.Now().Format("2006-01-02 15:04:05")
}

// IsNoRows reports whether err is sql.ErrNoRows.
func IsNoRows(err error) bool {
	return errors.Is(err, sql.ErrNoRows)
}
