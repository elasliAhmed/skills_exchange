// Video Call Module
let localStream;
let peerConnection;
let ws;
const configuration = { iceServers: [{ urls: 'stun:stun.l.google.com:19302' }] };

async function startVideoCall() {
    try {
        localStream = await navigator.mediaDevices.getUserMedia({ video: true, audio: true });
        document.getElementById('local-video').srcObject = localStream;
    } catch (err) {
        console.error('Error accessing media devices:', err);
    }
}

function initWebSocket() {
    ws = new WebSocket('ws://localhost:8080');
    
    ws.onopen = () => console.log('WebSocket connected');
    ws.onmessage = handleWebSocketMessage;
    ws.onerror = (err) => console.error('WebSocket error:', err);
}

async function handleWebSocketMessage(event) {
    const message = JSON.parse(event.data);
    
    switch (message.type) {
        case 'offer':
            await handleOffer(message);
            break;
        case 'answer':
            await handleAnswer(message);
            break;
        case 'candidate':
            await handleCandidate(message);
            break;
    }
}

async function createPeerConnection() {
    peerConnection = new RTCPeerConnection(configuration);
    
    localStream.getTracks().forEach(track => {
        peerConnection.addTrack(track, localStream);
    });
    
    peerConnection.ontrack = (event) => {
        document.getElementById('remote-video').srcObject = event.streams[0];
    };
    
    peerConnection.onicecandidate = (event) => {
        if (event.candidate) {
            ws.send(JSON.stringify({ type: 'candidate', candidate: event.candidate }));
        }
    };
}

async function startCall() {
    await startVideoCall();
    await createPeerConnection();
    
    const offer = await peerConnection.createOffer();
    await peerConnection.setLocalDescription(offer);
    
    ws.send(JSON.stringify({ type: 'offer', offer: offer }));
}

async function handleOffer(message) {
    await createPeerConnection();
    await peerConnection.setRemoteDescription(new RTCSessionDescription(message.offer));
    
    const answer = await peerConnection.createAnswer();
    await peerConnection.setLocalDescription(answer);
    
    ws.send(JSON.stringify({ type: 'answer', answer: answer }));
}

async function handleAnswer(message) {
    await peerConnection.setRemoteDescription(new RTCSessionDescription(message.answer));
}

async function handleCandidate(message) {
    await peerConnection.addIceCandidate(new RTCIceCandidate(message.candidate));
}

// Toggle camera
function toggleCamera() {
    const videoTrack = localStream.getVideoTracks()[0];
    if (videoTrack) {
        videoTrack.enabled = !videoTrack.enabled;
    }
}

// Toggle microphone
function toggleMic() {
    const audioTrack = localStream.getAudioTracks()[0];
    if (audioTrack) {
        audioTrack.enabled = !audioTrack.enabled;
    }
}

// End call
function endCall() {
    if (peerConnection) {
        peerConnection.close();
    }
    if (localStream) {
        localStream.getTracks().forEach(track => track.stop());
    }
    if (ws) {
        ws.close();
    }
    showPage('dashboard');
}

// Event listeners for video controls
document.getElementById('toggle-camera')?.addEventListener('click', toggleCamera);
document.getElementById('toggle-mic')?.addEventListener('click', toggleMic);
document.getElementById('end-call')?.addEventListener('click', endCall);