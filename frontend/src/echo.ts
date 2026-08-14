import Echo from 'laravel-echo'
import Pusher from 'pusher-js'
import api from '@/services/api'

window.Pusher = Pusher

const echo = new Echo({
    broadcaster: 'reverb',

    key: import.meta.env.VITE_REVERB_APP_KEY,

    wsHost: import.meta.env.VITE_REVERB_HOST,
    wsPort: Number(import.meta.env.VITE_REVERB_PORT),

    forceTLS: false,

    enabledTransports: ['ws'],

    // Private channels (conversation.{id}) need the current Bearer token on
    // the /broadcasting/auth call. The default pusher-js auth flow has no way
    // to attach it, so we route the request through the shared `api` axios
    // instance, which already injects it via its request interceptor.
    authorizer: (channel) => ({
        authorize(socketId, callback) {
            api
                .post('/broadcasting/auth', {
                    socket_id: socketId,
                    channel_name: channel.name,
                })
                .then((response) => callback(null, response.data))
                .catch((error) => callback(error, null))
        },
    }),
})

export default echo