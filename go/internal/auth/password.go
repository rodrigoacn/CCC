package auth

import (
	"crypto/rand"
	"encoding/hex"

	"golang.org/x/crypto/bcrypt"
)

func randomHex(nBytes int) string {
	b := make([]byte, nBytes)
	if _, err := rand.Read(b); err != nil {
		panic(err)
	}
	return hex.EncodeToString(b)
}

// PasswordMatches verifies a bcrypt hash produced by PHP's password_hash().
func PasswordMatches(hash, plain string) bool {
	return bcrypt.CompareHashAndPassword([]byte(hash), []byte(plain)) == nil
}

// HashPassword hashes a plaintext password with bcrypt (PASSWORD_DEFAULT).
func HashPassword(plain string) (string, error) {
	b, err := bcrypt.GenerateFromPassword([]byte(plain), bcrypt.DefaultCost)
	return string(b), err
}

// NewToken returns a 64-char hex token (same as bin2hex(random_bytes(32))).
func NewToken() string {
	return randomHex(32)
}
