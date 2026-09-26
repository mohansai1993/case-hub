@extends('layouts.admin')

@section('title', 'CaseHub Create Plan')

@section('content')
<div class="page-head">
  <h1 class="title-back">
    <a href="{{ route('admin.subscriptions') }}" class="back-btn" aria-label="Back to subscriptions">
      <svg viewBox="0 0 24 24"><path d="M15 18l-6-6 6-6"/></svg>
    </a>
    <span id="form-title">Create Subscription Plan</span>
  </h1>
  <p>Configure a new storage capacity tier and pricing model for client document vaults.</p>
</div>

<section class="card form-card">
  <form id="plan-form" novalidate>
    <div class="form-row">
      <div class="form-label-row">
        <label for="plan-name">Plan Name</label>
        <span class="section-label">Required</span>
      </div>
      <input class="input" id="plan-name" type="text" placeholder="Enter plan name (e.g. Enterprise Tier, Basic, Standard)" />
    </div>

    <div class="form-row">
      <div class="form-label-row">
        <label for="plan-storage">Storage Amount</label>
        <span class="section-label">Capacity Allocation</span>
      </div>
      <div class="input-group">
        <input class="input" id="plan-storage" type="number" min="1" placeholder="e.g. 500 or 5" />
        <div class="unit-toggle" role="group" aria-label="Storage unit">
          <button type="button" data-unit="MB">MB</button>
          <button type="button" data-unit="GB" class="is-active">GB</button>
        </div>
      </div>
    </div>

    <div class="form-row">
      <div class="form-label-row">
        <label for="plan-price">Price</label>
        <span class="section-label">INR (₹)</span>
      </div>
      <div class="input-prefix">
        <span>₹</span>
        <input class="input" id="plan-price" type="number" min="0" placeholder="Enter price amount (e.g. 299)" />
      </div>
    </div>

    <div class="form-row">
      <div class="form-label-row">
        <label>Payment Type</label>
      </div>
      <div class="pay-type">
        <div class="pay-type-head">
          <span class="pill-soft pill-strong">
            <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M8 12l3 3 5-6"/></svg>
            One-time payment
          </span>
          <span class="section-label">Fixed Archetype</span>
        </div>
        <p>System storage tiers operate on a non-recurring, one-time payment structure. Document quotas remain permanently tied to client legal matter storage.</p>
      </div>
    </div>

    <div class="form-row">
      <div class="form-label-row">
        <label for="plan-desc">Short Description</label>
        <span class="section-label">Client Facing</span>
      </div>
      <textarea class="input" id="plan-desc" rows="4" placeholder="Enter short description (e.g. Essential document vault for individual clients handling standard matter proceedings)"></textarea>
    </div>

    <label class="popular-check">
      <input type="checkbox" id="plan-popular" />
      <span>Mark as Popular Tier</span>
    </label>

    <p class="error-msg" id="form-error"></p>

    <div class="form-actions">
      <a href="{{ route('admin.subscriptions') }}" class="btn-inline btn-light">Cancel</a>
      <button type="submit" class="btn-inline btn-block-primary" id="submit-btn">Create Plan</button>
    </div>
  </form>
</section>
@endsection

@push('scripts')
<script>
// Plans are shared with {{ route('admin.subscriptions') }} through localStorage
const PLANS_KEY = "casehub-plans";
const DEFAULT_PLANS = [
  { id: "basic", name: "Basic", price: 99, storage: "500 MB", popular: false, desc: "Essential document vault for individual clients handling standard matter proceedings." },
  { id: "standard", name: "Standard", price: 199, storage: "1 GB", popular: true, desc: "Expanded capacity for corporate files, evidentiary exhibits, and continuous records." },
  { id: "premium", name: "Premium", price: 399, storage: "5 GB", popular: false, desc: "High-capacity tier for complex litigation dockets with multimedia and large forensic bundles." }
];

function loadPlans() {
  try {
    const saved = JSON.parse(localStorage.getItem(PLANS_KEY));
    if (Array.isArray(saved)) return saved;
  } catch (e) {}
  return DEFAULT_PLANS;
}

const form = document.getElementById("plan-form");
const nameInput = document.getElementById("plan-name");
const storageInput = document.getElementById("plan-storage");
const priceInput = document.getElementById("plan-price");
const descInput = document.getElementById("plan-desc");
const popularInput = document.getElementById("plan-popular");
const formError = document.getElementById("form-error");
const unitButtons = document.querySelectorAll(".unit-toggle button");
let unit = "GB";

function setUnit(value) {
  unit = value;
  unitButtons.forEach((b) => b.classList.toggle("is-active", b.dataset.unit === value));
}

unitButtons.forEach((b) => b.addEventListener("click", () => setUnit(b.dataset.unit)));

// Edit mode: {{ route('admin.create-plan') }}?edit=<plan id>
let plans = loadPlans();
const editId = new URLSearchParams(location.search).get("edit");
const editing = plans.find((p) => p.id === editId);

if (editing) {
  document.getElementById("form-title").textContent = "Edit Subscription Plan";
  document.getElementById("submit-btn").textContent = "Save Changes";
  nameInput.value = editing.name;
  const [amount, storageUnit] = editing.storage.split(" ");
  storageInput.value = amount;
  setUnit(storageUnit);
  priceInput.value = editing.price;
  descInput.value = editing.desc;
  popularInput.checked = editing.popular;
}

form.addEventListener("submit", (e) => {
  e.preventDefault();
  const name = nameInput.value.trim();
  const storage = Number(storageInput.value);
  const price = Number(priceInput.value);

  if (!name) return (formError.textContent = "Please enter a plan name.");
  if (!storage || storage <= 0) return (formError.textContent = "Please enter a valid storage amount.");
  if (priceInput.value === "" || price < 0) return (formError.textContent = "Please enter a valid price.");
  formError.textContent = "";

  const plan = {
    id: editing ? editing.id : `plan-${Date.now()}`,
    name,
    price,
    storage: `${storage} ${unit}`,
    popular: popularInput.checked,
    active: editing ? editing.active !== false : true,
    desc: descInput.value.trim()
  };

  // Only one plan can be the popular tier
  if (plan.popular) plans = plans.map((p) => ({ ...p, popular: false }));
  plans = editing ? plans.map((p) => (p.id === plan.id ? plan : p)) : [...plans, plan];

  // TODO: save the plan via backend API
  try { localStorage.setItem(PLANS_KEY, JSON.stringify(plans)); } catch (err) {}
  location.href = "{{ route('admin.subscriptions') }}";
});
</script>
@endpush
