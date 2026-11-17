<h1>Finance Cards</h1>
<?php if (Session::userIsLoggedIn()) { ?>
	<div class='new-list-item-container'>
		<span class='new-list-item-button'>
			<a href='/cards/add'>New Card</a>
		</span>
	</div>
<?php } ?>

<!-- echo out the system feedback (error and success messages) -->
<?php $this->renderFeedbackMessages(); ?>

<?php if ($this->cards) { ?>
	<?php foreach($this->cards as $key => $value) { ?>
		<div class="list-item">
			<span class="list-item-title"><?= $this->encodeHTML($value->name); ?></span>
			<span class="list-item-footer">Provider: <?= $this->encodeHTML($value->provider); ?></span>
			<form class="card-sync-form" method="post" action="<?= $this->encodeHTML(Config::get('URL') . 'cards/sync'); ?>">
				<input type="hidden" name="card_id" value="<?= (int) $value->id; ?>" />
				<input type="hidden" name="csrf_token" value="<?= $this->encodeHTML(Csrf::makeToken()); ?>" />
				<button type="submit">Sync</button>
				<span class="card-sync-status" role="status" aria-live="polite"></span>
			</form>
		</div>
	<?php } ?>
<?php } else { ?>
    <div>No cards found.</div>
<?php } ?>

<script>
	document.querySelectorAll('.card-sync-form').forEach((form) => {
		form.addEventListener('submit', async (event) => {
			event.preventDefault();
			const button = form.querySelector('button');
			const status = form.querySelector('.card-sync-status');
			if (button.disabled) {
				return;
			}
			button.disabled = true;
			button.textContent = 'Syncing…';
			status.textContent = '';

			try {
				const response = await fetch(form.action, {
					method: 'POST',
					credentials: 'same-origin',
					headers: { 'Accept': 'application/json' },
					body: new FormData(form),
				});
				const result = await response.json().catch(() => null);
				if (!response.ok || !result?.success) {
					throw new Error(result?.error || 'Unable to sync this card. Please try again.');
				}
				status.textContent = 'Sync complete. Transactions are queued for review.';
			} catch (error) {
				status.textContent = error.message || 'Unable to sync this card. Please try again.';
			} finally {
				button.disabled = false;
				button.textContent = 'Sync';
			}
		});
	});
</script>
