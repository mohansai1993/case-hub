// Shared roles data for roles.html, create-role.html and role-details.html.
// Saved in this browser (localStorage) until a backend exists.

const ROLES_KEY = "casehub-roles";

const PERMISSION_MODULES = [
  { id: "dashboard", name: "Dashboard", perms: ["View Dashboard"] },
  { id: "clients", name: "Client Management", perms: ["View Clients", "Add Client", "Edit Client", "Delete Client"] },
  { id: "lawyers", name: "Lawyer Management", perms: ["View Lawyers", "Add Lawyer", "Edit Lawyer", "Delete Lawyer"] },
  { id: "subscriptions", name: "Subscription Management", perms: ["View Subscriptions", "Manage Subscriptions"] },
  { id: "notifications", name: "Notifications", perms: ["View Notifications", "Create Notifications"] },
  { id: "settings", name: "Settings", perms: ["View Settings", "Manage Settings"] }
];

const ALL_PERMISSIONS = PERMISSION_MODULES.flatMap((m) => m.perms);

const DEFAULT_ROLES = [
  {
    id: "super-admin",
    name: "Super Admin",
    tag: "System Default",
    system: true,
    desc: "Full access to all admin features",
    status: "Active",
    created: "15 Aug 2026",
    perms: ALL_PERMISSIONS,
  },
  {
    id: "support-manager",
    name: "Support Manager",
    tag: "Client Desk",
    desc: "Manage client support and notifications",
    status: "Active",
    created: "12 Oct 2026",
    perms: ["View Dashboard", "View Clients", "Edit Client", "View Notifications", "Create Notifications"],
  },
  {
    id: "compliance-auditor",
    name: "Compliance Auditor",
    tag: "Security & Risk",
    desc: "Review audit logs, data retention policies, and SOC2 compliance records",
    status: "Active",
    created: "04 Nov 2026",
    perms: ["View Dashboard", "View Clients", "View Lawyers", "View Settings"],
  },
  {
    id: "billing-admin",
    name: "Billing Administrator",
    tag: "Finance Ops",
    desc: "Manage subscription tiers, client invoices, and payment disbursements",
    status: "Active",
    created: "16 Nov 2026",
    perms: ["View Dashboard", "View Clients", "View Subscriptions", "Manage Subscriptions"],
  },
  {
    id: "case-manager",
    name: "Case Manager",
    tag: "Litigation Unit",
    desc: "Manage clients, cases and case-related activities",
    status: "Active",
    created: "28 Sep 2026",
    perms: ["View Clients", "Add Client", "Edit Client", "View Notifications", "View Lawyers"],
  }
];

function loadRoles() {
  try {
    const saved = JSON.parse(localStorage.getItem(ROLES_KEY));
    if (Array.isArray(saved)) return saved;
  } catch (e) {}
  return DEFAULT_ROLES;
}

function saveRoles(roles) {
  // TODO: save roles via backend API
  try { localStorage.setItem(ROLES_KEY, JSON.stringify(roles)); } catch (e) {}
}

// Staff members — each one holds exactly one role (roleId).
// A role's "assigned users" are the staff members with that roleId.

const STAFF_KEY = "casehub-staff";

const DEFAULT_STAFF = [
  { id: "staff-1", name: "Arthur Vance", email: "arthur.vance@casehub.com", mobile: "+91 98100 22001", roleId: "super-admin", status: "Active", created: "15 Aug 2026", lastLogin: "Today, 09:05 AM", lastIp: "192.168.1.10 (Delhi Gateway)" },
  { id: "staff-2", name: "Rahul Sharma", email: "rahul@casehub.com", mobile: "+91 98765 43210", roleId: "support-manager", status: "Active", created: "28 Sep 2026", lastLogin: "Today, 10:42 AM", lastIp: "192.168.1.45 (Delhi Gateway)" },
  { id: "staff-3", name: "Priya Verma", email: "priya@casehub.com", mobile: "+91 99887 66554", roleId: "support-manager", status: "Active", created: "12 Oct 2026", lastLogin: "Yesterday, 04:15 PM", lastIp: "10.0.4.12 (Mumbai Office)" },
  { id: "staff-4", name: "Neha Kapoor", email: "neha.k@casehub.com", mobile: "+91 91234 56780", roleId: "compliance-auditor", status: "Active", created: "04 Nov 2026", lastLogin: "Today, 08:30 AM", lastIp: "10.0.2.31 (Bengaluru Office)" },
  { id: "staff-5", name: "Vikram Malhotra", email: "vikram.m@casehub.com", mobile: "+91 90000 11223", roleId: "billing-admin", status: "Active", created: "18 Nov 2026", lastLogin: "20 Nov 2026, 11:15 AM", lastIp: "10.0.4.18 (Mumbai Office)" },
  { id: "staff-6", name: "Priya Nair", email: "priya.nair@casehub.com", mobile: "+91 97654 32109", roleId: "case-manager", status: "Active", created: "30 Sep 2026", lastLogin: "Today, 11:20 AM", lastIp: "192.168.1.52 (Delhi Gateway)" },
  { id: "staff-7", name: "Amit Verma", email: "amit.verma@casehub.com", mobile: "+91 93456 78901", roleId: "case-manager", status: "Inactive", created: "02 Oct 2026", lastLogin: "Never", lastIp: "" }
];

function loadStaff() {
  try {
    const saved = JSON.parse(localStorage.getItem(STAFF_KEY));
    if (Array.isArray(saved)) return saved;
  } catch (e) {}
  return DEFAULT_STAFF;
}

function saveStaff(staff) {
  // TODO: save staff via backend API
  try { localStorage.setItem(STAFF_KEY, JSON.stringify(staff)); } catch (e) {}
}

function roleUsers(roleId, staff = loadStaff()) {
  return staff.filter((s) => s.roleId === roleId);
}

function escapeHtml(text) {
  const div = document.createElement("div");
  div.textContent = String(text);
  return div.innerHTML;
}

function initials(name) {
  return name.split(" ").map((w) => w[0]).slice(0, 2).join("").toUpperCase();
}

function formatDate(date) {
  return date.toLocaleDateString("en-GB", { day: "2-digit", month: "short", year: "numeric" });
}

const ROLE_ICONS = [
  '<path d="M12 2l8 4v6c0 5-3.5 8.5-8 10-4.5-1.5-8-5-8-10V6l8-4Z"/><path d="M9 12l2 2 4-4"/>',
  '<path d="M3 18v-6a9 9 0 0 1 18 0v6"/><path d="M21 19a2 2 0 0 1-2 2h-1v-6h3ZM3 19a2 2 0 0 0 2 2h1v-6H3Z"/>',
  '<rect x="4" y="3" width="16" height="18" rx="2"/><path d="M8 8h8M8 12h8M8 16h5"/>',
  '<rect x="2" y="5" width="20" height="14" rx="2"/><path d="M2 10h20"/>',
  '<rect x="3" y="7" width="18" height="13" rx="2"/><path d="M8 7V5a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>'
];

function roleIcon(role, roles) {
  const index = Math.max(0, roles.indexOf(role));
  return `<svg viewBox="0 0 24 24">${ROLE_ICONS[index % ROLE_ICONS.length]}</svg>`;
}
