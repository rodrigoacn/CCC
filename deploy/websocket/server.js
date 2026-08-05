const WebSocket = require('ws');
const mysql2 = require('mysql2/promise');

const PORT = process.env.WS_PORT || 3000;

const pool = mysql2.createPool({
  host: process.env.DB_HOST || 'localhost',
  user: process.env.DB_USER || 'classexpress',
  password: process.env.DB_PASS || '',
  database: process.env.DB_NAME || 'classexpress',
  waitForConnections: true,
  connectionLimit: 10,
  queueLimit: 0,
});

// rooms: Map<salaId, Map<userId, Set<WebSocket>>>
// Multiple WS connections per user allowed (web + mobile simultaneously)
const rooms = new Map();

function joinRoom(ws, salaId, userId) {
  if (!rooms.has(salaId)) rooms.set(salaId, new Map());
  const room = rooms.get(salaId);
  if (!room.has(userId)) room.set(userId, new Set());
  room.get(userId).add(ws);
  ws.roomInfo = { salaId, userId };
  console.log(`[join] sala=${salaId} userId=${userId} total=${room.size}`);
  broadcastRoomState(salaId);
}

function leaveRoom(ws) {
  const info = ws.roomInfo;
  if (!info) return;
  const { salaId, userId } = info;
  const room = rooms.get(salaId);
  if (!room) return;
  const userSockets = room.get(userId);
  if (userSockets) {
    userSockets.delete(ws);
    if (userSockets.size === 0) room.delete(userId);
  }
  if (room.size === 0) rooms.delete(salaId);
  console.log(`[leave] sala=${salaId} userId=${userId} remaining=${room.size}`);
  broadcastRoomState(salaId);
}

function broadcastRoomState(salaId) {
  const room = rooms.get(salaId);
  if (!room) return;
  const users = Array.from(room.keys()).map(uid => ({ userId: uid }));
  broadcastToRoom(salaId, null, { type: 'room_state', data: { users } });
}

function broadcastToRoom(salaId, excludeWs, msg) {
  const room = rooms.get(salaId);
  if (!room) return;
  const data = JSON.stringify(msg);
  for (const [, sockets] of room) {
    for (const ws of sockets) {
      if (ws !== excludeWs && ws.readyState === WebSocket.OPEN) {
        ws.send(data);
      }
    }
  }
}

async function handleChatSend(ws, data) {
  const info = ws.roomInfo;
  if (!info) return;
  const { salaId, userId } = info;
  const mensaje = (data.mensaje || '').trim();
  if (!mensaje) return;

  try {
    // Look up user's display name
    const [rows] = await pool.execute(
      'SELECT nombre FROM usuarios WHERE usuarioId = ?',
      [userId]
    );
    const alias = rows[0]?.nombre || 'Unknown';

    // Write to MySQL
    const [result] = await pool.execute(
      'INSERT INTO mensajes_chat (salaId, usuarioId, alias, mensaje, enviado_at) VALUES (?, ?, ?, ?, NOW())',
      [salaId, userId, alias, mensaje]
    );
    const mensajeId = result.insertId;

    const chatMsg = {
      type: 'chat',
      data: {
        mensajeId,
        alias,
        mensaje,
        enviado_at: new Date().toISOString().slice(0, 19).replace('T', ' '),
      },
    };

    // Broadcast to ALL in room including sender
    const room = rooms.get(salaId);
    if (room) {
      const dataStr = JSON.stringify(chatMsg);
      for (const [, sockets] of room) {
        for (const s of sockets) {
          if (s.readyState === WebSocket.OPEN) s.send(dataStr);
        }
      }
    }

    console.log(`[chat] sala=${salaId} userId=${userId} msgId=${mensajeId}`);
  } catch (err) {
    console.error('[chat] error:', err.message);
  }
}

const server = new WebSocket.Server({ port: PORT });
console.log(`WS server listening on port ${PORT}`);

server.on('connection', (ws) => {
  ws.on('message', async (raw) => {
    let msg;
    try {
      msg = JSON.parse(raw.toString());
    } catch {
      return;
    }

    switch (msg.type) {
      case 'join':
        joinRoom(ws, String(msg.salaId), Number(msg.userId));
        break;

      case 'leave':
        leaveRoom(ws);
        break;

      case 'signal':
        // Relay WebRTC signal to all other peers in room
        if (ws.roomInfo) {
          broadcastToRoom(ws.roomInfo.salaId, ws, { type: 'signal', data: msg.data });
        }
        break;

      case 'chat_send':
        await handleChatSend(ws, msg.data || {});
        break;

      default:
        break;
    }
  });

  ws.on('close', () => {
    leaveRoom(ws);
  });

  ws.on('error', () => {
    leaveRoom(ws);
  });
});
