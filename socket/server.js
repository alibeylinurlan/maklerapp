const { createServer } = require('http');
const { Server } = require('socket.io');
const Redis = require('ioredis');

const httpServer = createServer();

const io = new Server(httpServer, {
    cors: { origin: '*' },
    path: '/socket.io/',
    transports: ['websocket', 'polling'],
});

const subscriber = new Redis({ host: 'makler_redis', port: 6379 });

subscriber.on('connect', () => console.log('Redis connected'));
subscriber.on('error', (err) => console.error('Redis error:', err));

subscriber.subscribe('binokl-database-properties.new', (err, count) => {
    if (err) console.error('Subscribe error:', err);
    else console.log('Subscribed to', count, 'channel(s)');
});

subscriber.on('message', (channel, message) => {
    try {
        const data = JSON.parse(message);
        io.emit('property.created', data);
        console.log('Emitted property:', data.id);
    } catch (e) {
        console.error('Parse error:', e);
    }
});

io.on('connection', (socket) => {
    console.log('Client connected, total:', io.engine.clientsCount);
    socket.on('disconnect', () => {
        console.log('Client disconnected, total:', io.engine.clientsCount);
    });
});

httpServer.listen(3000, () => {
    console.log('Socket.IO server running on :3000');
});
