<template>
    <div class="org-node" :class="{ 'is-collapsed': collapsed, 'is-cluster-item': cluster }" :style="{ '--accent': cardAccent, '--line': cardAccent }">
        <div
            class="org-card"
            :class="cardClass"
            :data-person="node.id"
            :draggable="draggable ? 'true' : 'false'"
            role="button"
            tabindex="0"
            :aria-grabbed="dragging ? 'true' : 'false'"
            @click="onClick"
            @keydown.enter.prevent="onClick"
            @dragstart="onDragStart"
            @dragend="onDragEnd"
            @dragover="onDragOver"
            @dragleave="onDragLeave"
            @drop="onDrop"
        >
            <div class="org-card__photo">
                <img v-if="showPhoto" :src="node.avatar" :alt="node.name" @error="photoFailed = true">
                <span v-else>{{ initials }}</span>
                <i v-if="online" class="org-card__online" aria-hidden="true"></i>
            </div>
            <div class="org-card__name">{{ node.name || 'Unnamed' }}</div>
            <div class="org-card__role">{{ node.role_name || 'Team member' }}</div>
            <div v-if="peopleCount" class="org-card__count">{{ peopleCount }} {{ peopleCount === 1 ? 'person' : 'people' }}</div>
            <span v-if="node.id === currentUserId" class="org-card__you">You</span>
        </div>

        <button
            v-if="hasBody && !cluster"
            class="org-toggle"
            type="button"
            :aria-expanded="collapsed ? 'false' : 'true'"
            :title="collapsed ? 'Expand' : 'Collapse'"
            @click.stop="$emit('toggle', node.id)"
        >
            <iconify-icon :icon="collapsed ? 'lucide:plus' : 'lucide:minus'" width="12"></iconify-icon>
        </button>

        <div v-if="showMembers" class="org-cluster" :class="{ 'is-hot': clusterHot }" @dragover="onClusterOver" @dragleave="onClusterLeave" @drop="onClusterDrop">
            <div class="org-cluster__label">{{ node.name }} · {{ memberChildren.length }}</div>
            <div class="org-cluster__grid">
                <TeamTreeNode
                    v-for="member in memberChildren"
                    :key="member.id"
                    :node="member"
                    :accent="cardAccent"
                    :cluster="!(member.children && member.children.length)"
                    :current-user-id="currentUserId"
                    :highlighted-id="highlightedId"
                    :dragging-id="draggingId"
                    :collapsed-map="collapsedMap"
                    :can-drag="canDrag"
                    :can-drop-on="canDropOn"
                    @toggle="$emit('toggle', $event)"
                    @open="$emit('open', $event)"
                    @drag-start="$emit('drag-start', $event)"
                    @drag-end="$emit('drag-end')"
                    @drop="$emit('drop', $event)"
                />
            </div>
        </div>

        <div v-if="showLeaders" class="org-children">
            <div v-for="(child, index) in leaderChildren" :key="child.id" class="org-child">
                <TeamTreeNode
                    :node="child"
                    :accent="leaderAccent(index)"
                    :current-user-id="currentUserId"
                    :highlighted-id="highlightedId"
                    :dragging-id="draggingId"
                    :collapsed-map="collapsedMap"
                    :can-drag="canDrag"
                    :can-drop-on="canDropOn"
                    @toggle="$emit('toggle', $event)"
                    @open="$emit('open', $event)"
                    @drag-start="$emit('drag-start', $event)"
                    @drag-end="$emit('drag-end')"
                    @drop="$emit('drop', $event)"
                />
            </div>
        </div>
    </div>
</template>

<script>
function roleKey(node) {
    if (node?.role_key) return String(node.role_key).toLowerCase();
    return String(node?.role_name || '').toLowerCase().replace(/\s+/g, '_');
}

function isLeader(node) {
    const key = roleKey(node);
    return ['super_admin', 'admin', 'manager', 'team_lead', 'branch_admin'].includes(key);
}

function acceptsDrop(node) {
    const key = roleKey(node);
    return key === 'admin' || key === 'manager' || key.endsWith('_manager') || key === 'team_lead' || key === 'team lead' || key.includes('team_lead');
}

export default {
    name: 'TeamTreeNode',
    props: {
        node: { type: Object, required: true },
        accent: { type: String, default: '#1e3a78' },
        cluster: { type: Boolean, default: false },
        currentUserId: { type: Number, default: 0 },
        highlightedId: { type: Number, default: 0 },
        draggingId: { type: Number, default: 0 },
        collapsedMap: { type: Object, default: () => ({}) },
        canDrag: { type: Function, required: true },
        canDropOn: { type: Function, required: true },
    },
    emits: ['toggle', 'open', 'drag-start', 'drag-end', 'drop'],
    data() {
        return { photoFailed: false, hovering: false, clusterHover: false };
    },
    computed: {
        showPhoto() {
            return !!this.node.avatar && !this.photoFailed;
        },
        initials() {
            const parts = String(this.node.name || '').trim().split(/\s+/).filter(Boolean);
            if (!parts.length) return '?';
            return (parts[0][0] + (parts.length > 1 ? parts[parts.length - 1][0] : '')).toUpperCase();
        },
        online() {
            if (!this.node.last_login_at) return false;
            const then = new Date(this.node.last_login_at).getTime();
            if (Number.isNaN(then)) return false;
            return (Date.now() - then) / 60000 <= 15;
        },
        leaderChildren() {
            return (this.node.children || []).filter((child) => isLeader(child));
        },
        memberChildren() {
            return (this.node.children || []).filter((child) => !isLeader(child));
        },
        hasBody() {
            return this.leaderChildren.length > 0 || this.memberChildren.length > 0;
        },
        collapsed() {
            return !!this.collapsedMap[this.node.id];
        },
        showLeaders() {
            return !this.collapsed && this.leaderChildren.length > 0 && !this.cluster;
        },
        showMembers() {
            return !this.collapsed && this.memberChildren.length > 0 && !this.cluster;
        },
        peopleCount() {
            return (this.node.children || []).length;
        },
        cardAccent() {
            return this.node.accent || this.accent;
        },
        draggable() {
            return this.canDrag(this.node);
        },
        dragging() {
            return this.draggingId === this.node.id;
        },
        available() {
            return this.draggingId && this.draggingId !== this.node.id && (this.canDropOn(this.node) || acceptsDrop(this.node));
        },
        clusterHot() {
            return this.clusterHover && this.draggingId && this.canDropOn(this.node);
        },
        cardClass() {
            return {
                'is-you': this.node.id === this.currentUserId,
                'is-found': this.highlightedId === this.node.id,
                'is-dragging': this.dragging,
                'is-available': this.available,
                'is-hover': this.hovering && this.available,
                'is-draggable': this.draggable,
                'is-compact': this.cluster,
            };
        },
    },
    watch: {
        'node.avatar'() {
            this.photoFailed = false;
        },
        'node.id'() {
            this.photoFailed = false;
        },
    },
    methods: {
        leaderAccent(index) {
            const child = this.leaderChildren[index];
            return child?.accent || this.cardAccent;
        },
        onClick() {
            if (this.suppressClick) return;
            this.$emit('open', this.node);
        },
        onDragStart(event) {
            if (!this.draggable) {
                event.preventDefault();
                return;
            }
            this.suppressClick = true;
            event.dataTransfer.effectAllowed = 'move';
            event.dataTransfer.setData('text/plain', String(this.node.id));
            this.$emit('drag-start', this.node);
        },
        onDragEnd() {
            this.hovering = false;
            this.clusterHover = false;
            setTimeout(() => { this.suppressClick = false; }, 60);
            this.$emit('drag-end');
        },
        onDragOver(event) {
            if (!this.draggingId || this.draggingId === this.node.id) return;
            if (!this.canDropOn(this.node) && !acceptsDrop(this.node)) return;
            event.preventDefault();
            if (event.dataTransfer) event.dataTransfer.dropEffect = 'move';
            event.stopPropagation();
            this.hovering = true;
        },
        onDragLeave(event) {
            if (event.currentTarget.contains(event.relatedTarget)) return;
            this.hovering = false;
        },
        onDrop(event) {
            if (!this.draggingId || this.draggingId === this.node.id) return;
            if (!this.canDropOn(this.node) && !acceptsDrop(this.node)) return;
            event.preventDefault();
            event.stopPropagation();
            this.hovering = false;
            this.$emit('drop', this.node);
        },
        onClusterOver(event) {
            if (!this.draggingId || !this.canDropOn(this.node)) return;
            event.preventDefault();
            this.clusterHover = true;
        },
        onClusterLeave(event) {
            if (event.currentTarget.contains(event.relatedTarget)) return;
            this.clusterHover = false;
        },
        onClusterDrop(event) {
            if (!this.draggingId || !this.canDropOn(this.node)) return;
            event.preventDefault();
            event.stopPropagation();
            this.clusterHover = false;
            this.$emit('drop', this.node);
        },
    },
};
</script>

<style scoped>
.org-node {
    display: flex;
    flex-direction: column;
    align-items: center;
    position: relative;
    perspective: 900px;
}

.org-card {
    position: relative;
    width: 168px;
    margin-top: 28px;
    padding: 36px 12px 14px;
    background:
        linear-gradient(180deg, color-mix(in srgb, var(--accent) 10%, #fff) 0%, #fff 42%);
    border: 1px solid color-mix(in srgb, var(--accent) 22%, #e6e9f2);
    border-radius: 14px;
    box-shadow:
        0 1px 0 rgba(255, 255, 255, .9) inset,
        0 10px 18px rgba(11, 7, 54, .08),
        0 2px 4px rgba(11, 7, 54, .04);
    text-align: center;
    cursor: pointer;
    transform-style: preserve-3d;
    animation: org-rise .55s cubic-bezier(.16, .84, .32, 1) both;
    transition: transform .28s cubic-bezier(.16, .84, .32, 1), box-shadow .28s ease, border-color .2s ease;
}

.org-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 5px;
    border-radius: 14px 14px 0 0;
    background: linear-gradient(90deg, var(--accent), color-mix(in srgb, var(--accent) 55%, #fff));
}

.org-card::after {
    content: '';
    position: absolute;
    inset: 8px 10px auto;
    height: 18px;
    border-radius: 10px;
    background: linear-gradient(180deg, rgba(255, 255, 255, .7), transparent);
    pointer-events: none;
}

.org-card.is-compact {
    width: 150px;
    margin-top: 24px;
}

.org-card.is-draggable {
    cursor: grab;
}

.org-card.is-draggable:active {
    cursor: grabbing;
}

.org-card:hover {
    transform: translateY(-8px) rotateX(8deg) scale(1.03);
    box-shadow:
        0 1px 0 rgba(255, 255, 255, .9) inset,
        0 22px 34px color-mix(in srgb, var(--accent) 28%, transparent),
        0 8px 12px rgba(11, 7, 54, .08);
}

.org-card.is-you {
    box-shadow: 0 0 0 2px #e6c200, 0 10px 28px rgba(30, 41, 92, 0.07);
}

.org-card.is-available {
    border-color: var(--accent);
    box-shadow: 0 0 0 3px color-mix(in srgb, var(--accent) 22%, transparent);
}

.org-card.is-hover {
    transform: translateY(-10px) rotateX(10deg) scale(1.05);
    background: #fff;
}

.org-card.is-dragging {
    opacity: .45;
}

.org-card.is-found {
    z-index: 3;
    animation: org-pop 1s cubic-bezier(.16, .84, .32, 1);
}

.org-card__photo {
    position: absolute;
    top: -26px;
    left: 50%;
    transform: translateX(-50%);
    width: 54px;
    height: 54px;
    border-radius: 50%;
    border: 3px solid #fff;
    box-shadow: 0 4px 12px rgba(24, 34, 74, .16);
    overflow: hidden;
    background: color-mix(in srgb, var(--accent) 16%, #fff);
    color: var(--accent);
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: 15px;
}

.org-card__photo img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.org-card__online {
    position: absolute;
    right: 1px;
    bottom: 1px;
    width: 9px;
    height: 9px;
    border-radius: 50%;
    background: #22a06b;
    border: 2px solid #fff;
}

.org-card__name {
    color: var(--accent);
    font-weight: 700;
    font-size: 13.5px;
    line-height: 1.25;
    letter-spacing: -0.01em;
}

.org-card__role {
    margin-top: 3px;
    color: #8b93a7;
    font-size: 11px;
    line-height: 1.3;
}

.org-card__count {
    margin-top: 6px;
    color: #9aa3b5;
    font-size: 10px;
    letter-spacing: .02em;
}

.org-card__you {
    position: absolute;
    top: 8px;
    right: 8px;
    font-size: 9px;
    font-weight: 700;
    letter-spacing: .04em;
    text-transform: uppercase;
    color: #8a6d00;
    background: #fff6cc;
    border-radius: 99px;
    padding: 1px 6px;
}

.org-toggle {
    margin-top: 8px;
    width: 22px;
    height: 22px;
    border-radius: 50%;
    border: 1px solid #d5d9e6;
    background: #fff;
    color: #5c6480;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    z-index: 2;
    box-shadow: 0 2px 6px rgba(30, 41, 92, .08);
}

.org-toggle:hover {
    border-color: var(--accent, #1e3a78);
    color: var(--accent, #1e3a78);
}

.org-cluster {
    margin-top: 16px;
    padding: 14px 12px 8px;
    border: 1px solid color-mix(in srgb, var(--accent) 24%, #e4e7f1);
    border-top: 3px solid var(--accent, #733E87);
    border-radius: 16px;
    background: color-mix(in srgb, var(--accent) 7%, rgba(255, 255, 255, .82));
    box-shadow: 0 12px 24px rgba(11, 7, 54, .05);
    max-width: min(720px, 92vw);
    position: relative;
}

.org-cluster::before {
    content: '';
    position: absolute;
    top: -16px;
    left: 50%;
    width: 2px;
    height: 16px;
    background: var(--line, var(--accent, #4a4678));
    transform: translateX(-50%);
}

.org-cluster.is-hot {
    border-color: var(--accent, #6d5bd0);
    box-shadow: 0 0 0 3px color-mix(in srgb, var(--accent, #6d5bd0) 20%, transparent);
}

.org-cluster__label {
    text-align: center;
    font-size: 11px;
    color: #8b93a7;
    margin-bottom: 4px;
}

.org-cluster__grid {
    display: flex;
    flex-wrap: wrap;
    justify-content: center;
    gap: 8px 10px;
}

.org-children {
    display: flex;
    justify-content: center;
    align-items: flex-start;
    position: relative;
    padding-top: 22px;
}

.org-children::before {
    content: '';
    position: absolute;
    top: 0;
    left: 50%;
    width: 2px;
    height: 22px;
    background: var(--line, var(--accent, #4a4678));
    transform: translateX(-50%);
}

.org-child {
    position: relative;
    padding: 22px 12px 0;
    display: flex;
    justify-content: center;
}

.org-child::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 2px;
    background: var(--line, var(--accent, #4a4678));
}

.org-child::after {
    content: '';
    position: absolute;
    top: 0;
    left: 50%;
    width: 2px;
    height: 22px;
    background: var(--line, var(--accent, #4a4678));
    transform: translateX(-50%);
}

.org-child:first-child::before { left: 50%; }
.org-child:last-child::before { right: 50%; }
.org-child:only-child::before { display: none; }

@keyframes org-rise {
    from { opacity: 0; transform: translateY(18px) rotateX(16deg); }
    to { opacity: 1; transform: none; }
}

@keyframes org-pop {
    0% { transform: rotateY(-16deg) translateY(6px) scale(.96); box-shadow: 0 0 0 0 var(--accent); }
    45% { transform: translateY(-12px) rotateY(8deg) scale(1.06); box-shadow: 0 0 0 5px color-mix(in srgb, var(--accent) 35%, transparent); }
    100% { transform: none; }
}

@media (prefers-reduced-motion: reduce) {
    .org-card,
    .org-card.is-found,
    .org-card:hover {
        transition: none;
        animation: none;
        transform: none;
    }
}
</style>
