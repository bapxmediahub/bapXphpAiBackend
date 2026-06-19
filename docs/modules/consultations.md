# Consultation Communication

Customer and astrologer rooms live at `/consultation/{id}`. Access is restricted to the session customer, assigned astrologer, or admin.

Messages and browser WebRTC signaling use authenticated PHP endpoints under `/api/consultations/{id}`. Clients short-poll JSON collections; no CLI process or WebSocket server is required. WebRTC carries audio directly, while the PHP application records signaling, status, timestamps, credits, and analytics.

Astrologer accounts are created by admin and open `/astrologer` after the required initial password change. Admin credentials and consultation metrics are available under the Services navigation.
