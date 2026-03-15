# Real-Time Chat System – Installation & Usage

## Overview

The chat system allows:
- **Users** to start a conversation with a **property agent** from the property single page.
- **Agents** to chat with users and other agents.
- **Super Admin** to view all conversations in the Admin Chat Dashboard.

## 1. Database

Run the migration:

```bash
php artisan migrate
```

This creates:
- `conversations` – id, type, listing_id, timestamps
- `conversation_user` – conversation_id, user_id, last_read_at, timestamps
- `messages` – id, conversation_id, sender_id, message, read_at, timestamps

## 2. Backend (Laravel)

### API routes (already registered in `routes/api.php`)

All under `jwt.auth` middleware:

| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/api/chat/start` | Start or get conversation (body: `agent_id`, optional `listing_id`) |
| GET | `/api/chat/conversations` | List current user's conversations |
| GET | `/api/chat/messages/{conversation}` | Paginated messages (query: `page`) |
| POST | `/api/chat/send` | Send message (body: `conversation_id`, `message`) |
| POST | `/api/chat/read` | Mark conversation as read (body: `conversation_id`) |
| GET | `/api/chat/admin/conversations` | **Super Admin only** – all conversations (query: `user_id`, `agent_id`, `page`) |

### Broadcasting (real-time)

The `MessageSent` event is broadcast on private channel `user.{id}` for each participant.

- **Pusher**: Configure `config/broadcasting.php` and `.env` (`PUSHER_*`). Ensure `BROADCAST_DRIVER=pusher` (or your driver).
- **Laravel WebSockets**: Use the `laravel-websockets` package and point the driver to it.

Channel authorization for `user.{id}` must allow the authenticated user to listen to their own channel (already defined in `routes/web.php` if you use `user.{id}`).

### Policies

- `ConversationPolicy`: only participants (or Super Admin) can view a conversation.
- `MessagePolicy`: only participants (or Super Admin) can view messages.

## 3. Frontend (Vue 3)

### Components

- **ChatButton.vue** – “Chat with Agent” button (used on property page).
- **ChatPopup.vue** – Modal with conversation list or chat window.
- **ChatWindow.vue** – Message list, input, send, load more.
- **MessageBubble.vue** – Single message (left/right, avatar, time, read state).
- **ConversationList.vue** – List of conversations with preview and unread count.

### Property page integration

In `PropertyDetails/BlogOne.vue`:

1. “Chat with Agent” is shown when `property.agent` exists (main actions + sidebar).
2. Clicking it opens `ChatPopup` with that agent and optional `listing_id`; the conversation is started or resumed via `POST /api/chat/start`.

### Admin dashboard

- **Route**: `/admin/chat`
- **Component**: `pages/chat/AdminChatDashboard.vue`
- **Access**: Super Admin only (enforced by API `GET /api/chat/admin/conversations`).
- **Features**: List all conversations, filter by user ID / agent ID, open a conversation to view and send messages.

Add a sidebar/menu link for Super Admin, for example:

```html
<router-link v-if="isSuperAdmin" to="/admin/chat">Chat (Admin)</router-link>
```

## 4. Security

- All chat endpoints require JWT auth (`jwt.auth` middleware).
- Only participants (or Super Admin) can read conversations and messages.
- Input validation: message max length 5000; `agent_id` and `conversation_id` validated against DB.

## 5. Performance

- Messages are paginated (e.g. 30 per page); frontend can “load more” (older messages).
- Conversations list is paginated (e.g. 20 per page).

## 6. Optional: Echo (real-time on frontend)

To receive messages in real time in the browser:

1. Install Laravel Echo and the Pusher (or socket) client.
2. After login, subscribe to the private channel for the current user, e.g. `Echo.private('user.' + userId)`.
3. Listen for the `message.sent` event and append the message to the current conversation if `conversation_id` matches.

`ChatPopup.vue` already subscribes to `user.{id}` and listens for `.message.sent` when `window.Echo` is available.

## 7. Testing

1. Log in as a user, open a property that has an agent, click “Chat with Agent” and send a message.
2. Log in as that agent, open the same property (or conversations list) and reply.
3. Log in as Super Admin, go to `/admin/chat`, search and open any conversation.

## 8. File reference

**Backend**
- `app/Models/Conversation.php`, `app/Models/Message.php`
- `app/Http/Controllers/Api/ChatController.php`
- `app/Events/MessageSent.php`
- `app/Policies/ConversationPolicy.php`, `app/Policies/MessagePolicy.php`
- `database/migrations/2026_03_13_100000_create_conversations_table.php`

**Frontend**
- `resources/js/components/chat/ChatButton.vue`, `ChatPopup.vue`, `ChatWindow.vue`, `MessageBubble.vue`, `ConversationList.vue`
- `resources/js/pages/chat/AdminChatDashboard.vue`
- Integration in `resources/js/components/alllisting/PropertyDetails/BlogOne.vue`
- Route: `resources/js/router.js` – `/admin/chat` → `AdminChatDashboard`
