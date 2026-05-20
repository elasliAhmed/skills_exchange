// WebSocket Signaling Server for WebRTC
const WebSocket = require('ws');
const http = require('http');

const server = http.createServer();
const wss = new WebSocket.Server({ server });

const rooms = new Map();

wss.on('connection', (ws) => {
    console.log('New client connected');
    
    ws.on('message', (message) => {
        try {
            const data = JSON.parse(message);
            handleMessage(ws, data);
        } catch (error) {
            console.error('Error parsing message:', error);
        }
    });

    ws.on('close', () => {
        console.log('Client disconnected');
        // Remove from rooms
        rooms.forEach((clients, roomId) => {
            if (clients.has(ws)) {
                clients.delete(ws);
                if (clients.size === 0) rooms.delete(roomId);
            }
        });
    });
});

function handleMessage(ws, data) {
    switch (data.type) {
        case 'join':
            handleJoin(ws, data.roomId);
            break;
        case 'offer':
        case 'answer':
        case 'candidate':
            forwardMessage(ws, data);
            break;
    }
}

function handleJoin(ws, roomId) {
    if (!rooms.has(roomId)) {
        rooms.set(roomId, new Set());
    }
    rooms.get(roomId).add(ws);
    ws.roomId = roomId;
}

function forwardMessage(sender, data) {
    const room = rooms.get(sender.roomId);
    if (room) {
        room.forEach(client => {
            if (client !== sender && client.readyState === WebSocket.OPEN) {
                client.send(JSON.stringify(data));
            }
        });
    }
}

const PORT = process.env.PORT || 8080;
server.listen(PORT, () => {
    console.log(`Signaling server running on port ${PORT}`);
});