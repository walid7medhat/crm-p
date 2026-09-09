import { ref } from 'vue'
import api, { setAuthToken } from '@/plugins/axios'
import { API_ENDPOINTS } from '@/config/api'

const IMPERSONATOR_TOKEN_KEY = 'impersonator_token'
const IMPERSONATOR_USER_KEY = 'impersonator_user'

function readStoredUser(raw) {
  try {
    return raw ? JSON.parse(raw) : null
  } catch {
    return null
  }
}

/** Reactive so the "Return to Super Admin" banner updates without a full reload. */
const impersonatorUser = ref(readStoredUser(localStorage.getItem(IMPERSONATOR_USER_KEY)))

function storeSessionUser(userData) {
  localStorage.setItem(
    'user',
    JSON.stringify({
      id: userData.id,
      name: userData.name,
      email: userData.email,
      phone: userData.phone,
      avatar: userData.avatar,
      roles: userData.roles,
      permissions: userData.permissions,
      role_name: userData.role_name,
      is_listing_team: userData.is_listing_team,
      admin_parent_name: userData.admin_parent_name,
    })
  )
}

export function useImpersonation() {
  /** Super admin only — mint a token for `user` and switch into their session. */
  async function switchToUser(user) {
    const response = await api.post(API_ENDPOINTS.USER_IMPERSONATE(user.id))
    const { token, user: targetUser } = response.data?.data || {}
    if (!token || !targetUser) {
      throw new Error('Switch account did not return a token')
    }

    // Stash the current (super admin) session — only if we aren't already
    // impersonating someone, so a chain of switches always returns to the
    // original super admin, not to whichever account was active mid-chain.
    if (!localStorage.getItem(IMPERSONATOR_TOKEN_KEY)) {
      const currentToken = localStorage.getItem('token')
      const currentUser = localStorage.getItem('user')
      if (currentToken && currentUser) {
        localStorage.setItem(IMPERSONATOR_TOKEN_KEY, currentToken)
        localStorage.setItem(IMPERSONATOR_USER_KEY, currentUser)
      }
    }

    setAuthToken(token)
    storeSessionUser(targetUser)
    impersonatorUser.value = readStoredUser(localStorage.getItem(IMPERSONATOR_USER_KEY))

    window.location.href = '/'
  }

  /** Restore the stashed super admin session (no new login needed). */
  function returnToSuperAdmin() {
    const originalToken = localStorage.getItem(IMPERSONATOR_TOKEN_KEY)
    const originalUser = localStorage.getItem(IMPERSONATOR_USER_KEY)
    if (!originalToken || !originalUser) return

    setAuthToken(originalToken)
    localStorage.setItem('user', originalUser)
    localStorage.removeItem(IMPERSONATOR_TOKEN_KEY)
    localStorage.removeItem(IMPERSONATOR_USER_KEY)
    impersonatorUser.value = null

    window.location.href = '/'
  }

  return {
    impersonatorUser,
    switchToUser,
    returnToSuperAdmin,
  }
}
