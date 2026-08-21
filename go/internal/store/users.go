package store

import (
	"context"
	"database/sql"
	"errors"
	"strconv"
	"strings"
)

// ErrNotFound is returned when a row is expected but missing.
var ErrNotFound = errors.New("not found")

// User mirrors the `usuarios` row plus derived public fields.
type User struct {
	ID              int
	Nombre          string
	Email           string
	Username        string
	Password        string
	Rol             string
	Verificado      bool
	Avatar          string
	Biografia       string
	PaisID          int
	Creditos        int
	Calificacion    float64
	NumResenas      int
	IdiomaPreferido string
	UltimaMateria   int
	LastRoleSwitch  sql.NullTime
}

const selectUserCols = `usuarioId, nombre, email, username, password, rol, verificado, avatar, biografia, pais_id, creditos, calificacion, num_resenas, idioma_preferido, ultimaMateria, last_role_switch`

func scanUser(row interface{ Scan(...any) error }) (*User, error) {
	var u User
	var biografia sql.NullString
	var paisID sql.NullInt64
	var creditos, calificacion sql.NullString
	var ultimaMateria sql.NullInt64
	if err := row.Scan(
		&u.ID, &u.Nombre, &u.Email, &u.Username, &u.Password, &u.Rol,
		&u.Verificado, &u.Avatar, &biografia, &paisID, &creditos,
		&calificacion, &u.NumResenas, &u.IdiomaPreferido, &ultimaMateria,
		&u.LastRoleSwitch,
	); err != nil {
		return nil, err
	}
	u.Biografia = biografia.String
	if paisID.Valid {
		u.PaisID = int(paisID.Int64)
	}
	u.Creditos = parseDecimalInt(creditos)
	u.Calificacion = parseDecimalFloat(calificacion)
	if ultimaMateria.Valid {
		u.UltimaMateria = int(ultimaMateria.Int64)
	}
	return &u, nil
}

func parseDecimalInt(s sql.NullString) int {
	if !s.Valid || strings.TrimSpace(s.String) == "" {
		return 0
	}
	f, err := strconv.ParseFloat(strings.TrimSpace(s.String), 64)
	if err != nil {
		return 0
	}
	return int(f)
}

func parseDecimalFloat(s sql.NullString) float64 {
	if !s.Valid || strings.TrimSpace(s.String) == "" {
		return 0
	}
	f, err := strconv.ParseFloat(strings.TrimSpace(s.String), 64)
	if err != nil {
		return 0
	}
	return f
}

// UserStore provides user queries against the shared pool.
type UserStore struct {
	DB *sql.DB
}

// GetByEmail returns the user with the given email, or ErrNotFound.
func (s *UserStore) GetByEmail(ctx context.Context, email string) (*User, error) {
	row := s.DB.QueryRowContext(ctx,
		"SELECT "+selectUserCols+" FROM usuarios WHERE email = ? AND eliminado = 0", email)
	u, err := scanUser(row)
	if errors.Is(err, sql.ErrNoRows) {
		return nil, ErrNotFound
	}
	return u, err
}

// Languages returns the user's language names.
func (s *UserStore) Languages(ctx context.Context, userID int) ([]string, error) {
	rows, err := s.DB.QueryContext(ctx,
		"SELECT i.nombre FROM usuario_idiomas ui JOIN idiomas i ON i.idiomaId = ui.idiomaId WHERE ui.usuarioId = ? ORDER BY i.nombre",
		userID)
	if err != nil {
		return nil, err
	}
	defer rows.Close()

	out := []string{}
	for rows.Next() {
		var name string
		if err := rows.Scan(&name); err != nil {
			return nil, err
		}
		out = append(out, name)
	}
	return out, rows.Err()
}

// InsertMobileToken registers a token for the user (idempotent per user).
func (s *UserStore) InsertMobileToken(ctx context.Context, userID int, token string) error {
	_, err := s.DB.ExecContext(ctx,
		"INSERT IGNORE INTO mobile_tokens (usuario_id, token) VALUES (?, ?)", userID, token)
	return err
}

// LastRoleSwitchStr formats the value the same way MySQL DATETIME is returned.
func (u *User) LastRoleSwitchStr() string {
	if !u.LastRoleSwitch.Valid {
		return ""
	}
	return u.LastRoleSwitch.Time.Format("2006-01-02 15:04:05")
}
