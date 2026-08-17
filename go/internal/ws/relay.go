// Package ws ports deploy/websocket/server.js: an in-memory WebSocket relay
// for classroom real-time data (WebRTC signaling + chat) at /ws/.
package ws

import (
	"context"
	"encoding/json"
	"log"
	"net/http"
	"strings"
	"sync"
	"time"

	"github.com/gorilla/websocket"

	"classexpress/internal/store"
)

// upgrader mirrors the Node ws.Server defaults (all origins accepted).
var upgrader = websocket.Upgrader{
	ReadBufferSize:  1024,
	WriteBufferSize: 1024,
	CheckOrigin:     func(r *http.Request) bool { return true },
}

// connWrap serializes writes on a single socket (gorilla requires at most one
// concurrent writer).
type connWrap struct {
	conn *websocket.Conn
	mu   sync.Mutex
}

func (c *connWrap) writeText(p []byte) error {
	c.mu.Lock()
	defer c.mu.Unlock()
	return c.conn.WriteMessage(websocket.TextMessage, p)
}

// Hub relays room messages between clients.
type Hub struct {
	mu    sync.Mutex
	rooms map[string]map[int64]map[*connWrap]bool // salaId -> userId -> sockets
	db    *store.DB
}

// NewHub builds a relay hub backed by db (used to persist chat messages).
func NewHub(db *store.DB) *Hub {
	return &Hub{rooms: make(map[string]map[int64]map[*connWrap]bool), db: db}
}

// incoming is the client->server message. Both sala.php and useVideoCall.ts
// send signals as {type:'signal', tipo, payload} (top level).
type incoming struct {
	Type    string         `json:"type"`
	SalaID  any            `json:"salaId"`
	UserID  any            `json:"userId"`
	Data    map[string]any `json:"data"`
	Tipo    string         `json:"tipo"`
	Payload string         `json:"payload"`
}

type connState struct {
	conn   *connWrap
	salaID string
	userID int64
	left   bool
}

// ServeHTTP upgrades the connection and runs the message loop.
func (h *Hub) ServeHTTP(w http.ResponseWriter, r *http.Request) {
	conn, err := upgrader.Upgrade(w, r, nil)
	if err != nil {
		log.Printf("ws: upgrade: %v", err)
		return
	}
	st := &connState{conn: &connWrap{conn: conn}}
	defer func() {
		h.leave(st)
		_ = conn.Close()
	}()

	for {
		var msg incoming
		if err := conn.ReadJSON(&msg); err != nil {
			h.leave(st)
			return
		}
		switch msg.Type {
		case "join":
			h.join(st, store.Str(msg.SalaID), store.Int(msg.UserID))
		case "leave":
			h.leave(st)
		case "signal":
			h.signal(st, msg)
		case "chat_send":
			h.chatSend(st, msg)
		}
	}
}

// join registers the socket in the room and notifies everyone (mirrors server.js).
func (h *Hub) join(st *connState, salaID string, userID int64) {
	if st.left || salaID == "" || userID == 0 {
		return
	}
	st.salaID = salaID
	st.userID = userID

	h.mu.Lock()
	if h.rooms[salaID] == nil {
		h.rooms[salaID] = make(map[int64]map[*connWrap]bool)
	}
	room := h.rooms[salaID]
	if room[userID] == nil {
		room[userID] = make(map[*connWrap]bool)
	}
	room[userID][st.conn] = true
	h.mu.Unlock()

	h.broadcastRoomState(salaID)
}

// leave removes the socket from its room (idempotent).
func (h *Hub) leave(st *connState) {
	if st.left {
		return
	}
	st.left = true
	salaID := st.salaID

	h.mu.Lock()
	if room := h.rooms[salaID]; room != nil {
		if conns := room[st.userID]; conns != nil {
			delete(conns, st.conn)
			if len(conns) == 0 {
				delete(room, st.userID)
			}
		}
		if len(room) == 0 {
			delete(h.rooms, salaID)
		}
	}
	h.mu.Unlock()

	if salaID != "" {
		h.broadcastRoomState(salaID)
	}
}

// signal relays a WebRTC signal to every other peer in the room. The relay
// forwards the original data object (or builds one from the top-level fields
// sala.php/useVideoCall.ts actually send), unlike the Node server which lost
// the payload.
func (h *Hub) signal(st *connState, msg incoming) {
	if st.left || st.salaID == "" {
		return
	}
	data := msg.Data
	if data == nil {
		data = map[string]any{"tipo": msg.Tipo, "payload": msg.Payload}
	}
	h.broadcast(st.salaID, st.conn, map[string]any{"type": "signal", "data": data})
}

// chatSend persists a chat message and broadcasts it to the whole room
// (including the sender), mirroring server.js.
func (h *Hub) chatSend(st *connState, msg incoming) {
	if st.left || st.salaID == "" || h.db == nil {
		return
	}
	mensaje := strings.TrimSpace(store.Str(msg.Data["mensaje"]))
	if mensaje == "" {
		return
	}

	ctx, cancel := context.WithTimeout(context.Background(), 5*time.Second)
	defer cancel()

	alias := "Unknown"
	if row, err := h.db.QueryOne(ctx, "SELECT nombre FROM usuarios WHERE usuarioId = ?", st.userID); err == nil && row != nil {
		if n := store.Str(row["nombre"]); n != "" {
			alias = n
		}
	}
	msgID, err := h.db.Exec(ctx,
		"INSERT INTO mensajes_chat (salaId, usuarioId, alias, mensaje, enviado_at) VALUES (?, ?, ?, ?, NOW())",
		st.salaID, st.userID, alias, mensaje)
	if err != nil {
		log.Printf("ws: chat insert: %v", err)
		return
	}

	h.broadcastAll(st.salaID, map[string]any{
		"type": "chat",
		"data": map[string]any{
			"mensajeId":  msgID,
			"alias":      alias,
			"mensaje":    mensaje,
			"enviado_at": time.Now().UTC().Format("2006-01-02 15:04:05"),
		},
	})
}

// broadcast sends msg to all sockets in the room except exclude.
func (h *Hub) broadcast(salaID string, exclude *connWrap, msg any) {
	data, err := json.Marshal(msg)
	if err != nil {
		return
	}
	h.mu.Lock()
	defer h.mu.Unlock()
	for _, conns := range h.rooms[salaID] {
		for c := range conns {
			if c != exclude {
				if err := c.writeText(data); err != nil {
					log.Printf("ws: write: %v", err)
				}
			}
		}
	}
}

// broadcastAll sends msg to every socket in the room, including the sender.
func (h *Hub) broadcastAll(salaID string, msg any) {
	data, err := json.Marshal(msg)
	if err != nil {
		return
	}
	h.mu.Lock()
	defer h.mu.Unlock()
	for _, conns := range h.rooms[salaID] {
		for c := range conns {
			if err := c.writeText(data); err != nil {
				log.Printf("ws: write: %v", err)
			}
		}
	}
}

// broadcastRoomState notifies everyone in the room of the current participant
// list (mirrors server.js).
func (h *Hub) broadcastRoomState(salaID string) {
	h.mu.Lock()
	room := h.rooms[salaID]
	users := make([]map[string]any, 0, len(room))
	for uid := range room {
		users = append(users, map[string]any{"userId": uid})
	}
	h.mu.Unlock()

	h.broadcastAll(salaID, map[string]any{
		"type": "room_state",
		"data": map[string]any{"users": users},
	})
}
