@extends('layouts.admin')

@section('title', $plan ? 'CaseHub Edit Plan' : 'CaseHub Create Plan')

@section('content')
<div class="page-head">
  <h1 class="title-back">
    <a href="{{ route('admin.subscriptions') }}" class="back-btn" aria-label="Back to subscriptions">
      <svg viewBox="0 0 24 24"><path d="M15 18l-6-6 6-6"/></svg>
    </a>
    <span id="form-title">{{ $plan ? 'Edit Subscription Plan' : 'Create Subscription Plan' }}</span>
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
      <input class="input" id="plan-name" type="text" maxlength="255" value="{{ $plan->name ?? '' }}" placeholder="Enter plan name (e.g. Enterprise Tier, Basic, Standard)" />
    </div>

    <div class="form-row">
      <div class="form-label-row">
        <label for="plan-storage">Storage Amount</label>
        <span class="section-label">Capacity Allocation</span>
      </div>
      <div class="input-group">
        <input class="input" id="plan-storage" type="number" min="1" value="{{ $plan->storage_amount ?? '' }}" placeholder="e.g. 500 or 5" />
        <div class="unit-toggle" role="group" aria-label="Storage unit">
          <button type="button" data-unit="MB" class="{{ ($plan->storage_unit->value ?? 'GB') === 'MB' ? 'is-active' : '' }}">MB</button>
          <button type="button" data-unit="GB" class="{{ ($plan->storage_unit->value ?? 'GB') === 'GB' ? 'is-active' : '' }}">GB</button>
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
        <input class="input" id="plan-price" type="number" min="0" value="{{ $plan->price ?? '' }}" placeholder="Enter price amount (e.g. 299)" />
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
      <textarea class="input" id="plan-desc" rows="4" maxlength="1000" placeholder="Enter short description (e.g. Essential document vault for individual clients handling standard matter proceedings)">{{ $plan->description ?? '' }}</textarea>
    </div>

    <label class="popular-check">
      <input type="checkbox" id="plan-popular" @checked($plan->is_popular ?? false) />
      <span>Mark as Popular Tier</span>
    </label>

    <p class="error-msg" id="form-error"></p>

    <div class="form-actions">
      <a href="{{ route('admin.subscriptions') }}" class="btn-inline btn-light">Cancel</a>
      <button type="submit" class="btn-inline btn-block-primary" id="submit-btn">{{ $plan ? 'Save Changes' : 'Create Plan' }}</button>
    </div>
  </form>
</section>
@endsection

@push('scripts')
<script>
(function () {
  "use strict";

  var CSRF = document.querySelector('meta[name="csrf-token"]').content;
  var unitButtons = document.querySelectorAll(".unit-toggle button");
  var unit = "{{ $plan->storage_unit->value ?? 'GB' }}";

  function setUnit(value) {
    unit = value;
    unitButtons.forEach(function (b) { b.classList.toggle("is-active", b.dataset.unit === value); });
  }

  unitButtons.forEach(function (b) { b.addEventListener("click", function () { setUnit(b.dataset.unit); }); });

  var form = document.getElementById("plan-form");
  var nameInput = document.getElementById("plan-name");
  var storageInput = document.getElementById("plan-storage");
  var priceInput = document.getElementById("plan-price");
  var descInput = document.getElementById("plan-desc");
  var popularInput = document.getElementById("plan-popular");
  var formError = document.getElementById("form-error");
  var submitBtn = document.getElementById("submit-btn");

  form.addEventListener("submit", function (e) {
    e.preventDefault();

    var name = nameInput.value.trim();
    var storage = Number(storageInput.value);
    var price = Number(priceInput.value);

    if (!name) { formError.textContent = "Please enter a plan name."; return; }
    if (!storage || storage <= 0) { formError.textContent = "Please enter a valid storage amount."; return; }
    if (priceInput.value === "" || price < 0) { formError.textContent = "Please enter a valid price."; return; }
    formError.textContent = "";
    submitBtn.disabled = true;

    fetch("{{ $plan ? route('admin.plans.update', $plan) : route('admin.plans.store') }}", {
      method: "{{ $plan ? 'PUT' : 'POST' }}",
      headers: {
        "Content-Type": "application/json",
        "Accept": "application/json",
        "X-CSRF-TOKEN": CSRF,
        "X-Requested-With": "XMLHttpRequest"
      },
      body: JSON.stringify({
        name: name,
        storage_amount: storage,
        storage_unit: unit,
        price: price,
        description: descInput.value.trim(),
        is_popular: popularInput.checked
      })
    }).then(function (res) {
      return res.json().catch(function () { return {}; }).then(function (body) { return { ok: res.ok, body: body }; });
    }).then(function (result) {
      if (!result.ok) {
        var errors = result.body.errors;
        formError.textContent = errors ? Object.values(errors)[0][0] : (result.body.message || "Could not save this plan.");
        submitBtn.disabled = false;
        return;
      }
      window.location.href = "{{ route('admin.subscriptions') }}";
    }).catch(function () {
      formError.textContent = "Could not reach the server. Please try again.";
      submitBtn.disabled = false;
    });
  });
})();
</script>
@endpush
