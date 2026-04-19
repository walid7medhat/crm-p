<template>
  <div class="user-invitations">
    <div class="card">
      <div class="card-header">
        <h6 class="ui-h-sub">Invite New Users</h6>
      </div>
      <div class="card-body">
        <form @submit.prevent="sendInvitations">
          <div class="mb-3">
            <label for="emails" class="form-label">Email Addresses</label>
            <textarea
              id="emails"
              v-model="emails"
              class="form-control"
              placeholder="Enter email addresses (one per line)"
              rows="4"
              :disabled="loading"
            ></textarea>
            <div class="form-text">
              Enter email addresses, one per line
            </div>
          </div>
          
          <button 
            type="submit" 
            class="btn btn-primary"
            :disabled="loading || !emails.trim()"
          >
            <span v-if="loading" class="spinner-border spinner-border-sm me-2"></span>
            {{ loading ? 'Sending...' : 'Send Invitations' }}
          </button>
        </form>

        <div v-if="message" class="alert alert-success mt-3">
          {{ message }}
        </div>

        <div v-if="error" class="alert alert-danger mt-3">
          {{ error }}
        </div>
      </div>
    </div>

    <!-- Sent Invitations List -->
    <div class="card mt-4">
      <div class="card-header">
        <h6 class="ui-h-mini">Sent Invitations</h6>
      </div>
      <div class="card-body">
        <div v-if="invitations.length === 0" class="text-muted">
          No invitations sent yet
        </div>
        
        <div v-else class="table-responsive">
          <table class="table table-striped">
            <thead>
              <tr>
                <th>Email</th>
                <th>Status</th>
                <th>Sent Date</th>
                <th>Expires At</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="invitation in invitations" :key="invitation.id">
                <td>{{ invitation.email }}</td>
                <td>
                  <span :class="getStatusClass(invitation)">
                    {{ getStatusText(invitation) }}
                  </span>
                </td>
                <td>{{ formatDate(invitation.created_at) }}</td>
                <td>{{ formatDate(invitation.expires_at) }}</td>
                <td>
                  <button 
                    v-if="!invitation.used && !isExpired(invitation)"
                    @click="resendInvitation(invitation.id)"
                    class="btn btn-sm btn-outline-primary"
                    :disabled="resending === invitation.id"
                  >
                    <span v-if="resending === invitation.id" class="spinner-border spinner-border-sm me-1"></span>
                    Resend
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import axios from 'axios';

export default {
  name: 'UserInvitations',
  data() {
    return {
      emails: '',
      loading: false,
      message: '',
      error: '',
      invitations: [],
      resending: null
    };
  },
  mounted() {
    this.loadInvitations();
  },
  methods: {
 // In your Vue component methods
async sendInvitations() {
  this.loading = true;
  this.message = '';
  this.error = '';

  try {
    const token = localStorage.getItem('token');
    const emailList = this.emails
      .split('\n')
      .map(email => email.trim())
      .filter(email => email);

    const response = await axios.post('/api/user-invitations', {
      emails: emailList
    }, {
      headers: {
        Authorization: `Bearer ${token}`
      }
    });

    this.message = response.data.message;
    this.emails = '';
    this.loadInvitations();
  } catch (error) {
    console.error('Send invitations error:', error);
    if (error.response?.status === 401) {
      this.error = 'Please login again';
      localStorage.removeItem('token');
      localStorage.removeItem('user');
      window.location.href = '/login';
    } else {
      this.error = error.response?.data?.message || 'Error sending invitations';
    }
  } finally {
    this.loading = false;
  }
},

    async loadInvitations() {
      try {
        const response = await axios.get('/api/user-invitations');
        this.invitations = response.data;
      } catch (error) {
        console.error('Error loading invitations:', error);
      }
    },

    async resendInvitation(id) {
      this.resending = id;
      try {
        await axios.post(`/api/user-invitations/${id}/resend`);
        this.message = 'Invitation resent successfully';
        this.loadInvitations();
      } catch (error) {
        this.error = error.response?.data?.message || 'Error resending invitation';
      } finally {
        this.resending = null;
      }
    },

    getStatusClass(invitation) {
      if (invitation.used) return 'badge bg-success';
      if (this.isExpired(invitation)) return 'badge bg-danger';
      return 'badge bg-primary';
    },

    getStatusText(invitation) {
      if (invitation.used) return 'Used';
      if (this.isExpired(invitation)) return 'Expired';
      return 'Active';
    },

    isExpired(invitation) {
      return new Date(invitation.expires_at) < new Date();
    },

    formatDate(date) {
      return new Date(date).toLocaleDateString('en-US');
    }
  }
};
</script>

<style scoped>
.user-invitations {
  max-width: 1000px;
  margin: 0 auto;
}

.badge {
  font-size: 0.8em;
}
</style>