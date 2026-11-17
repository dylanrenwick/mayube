<h3>Transaction Queue</h3>

<?php $this->renderFeedbackMessages(); ?>

<?php if (empty($this->transactions)) { ?>
	<p>No new transactions to review.</p>
<?php } else { ?>
	<style>
		.queue-toolbar { display: flex; flex-wrap: wrap; gap: 1rem; align-items: center; margin-bottom: 1rem; }
		.queue-actions { display: flex; gap: .5rem; }
		.queue-actions[hidden] { display: none; }
		.queue-grid { overflow-x: auto; }
		.queue-table { width: 100%; border-collapse: collapse; }
		.queue-table th, .queue-table td { padding: .75rem; border-bottom: 1px solid #888; text-align: left; }
		.queue-table .queue-amount { text-align: right; white-space: nowrap; }
		.queue-table select { width: 100%; min-width: 10rem; }
	</style>
	<?php $canAccept = !empty($this->transaction_types); ?>
	<form id="queue-form" method="post" action="<?= $this->encodeHTML(Config::get('URL') . 'finance/reviewQueue'); ?>" novalidate>
		<input type="hidden" name="csrf_token" value="<?= $this->encodeHTML(Csrf::makeToken()); ?>" />
		<div class="queue-toolbar">
			<button id="queue-accept-all" type="submit" value="accept_all" <?= $canAccept ? '' : 'disabled'; ?>>Accept All</button>
			<div id="queue-selected-actions" class="queue-actions" hidden>
				<button type="submit" value="accept" <?= $canAccept ? '' : 'disabled'; ?>>Accept Selected</button>
				<button type="submit" value="reject">Reject Selected</button>
			</div>
			<span id="queue-selection-count" role="status" aria-live="polite"></span>
		</div>
		<?php if (empty($this->transaction_types)) { ?>
			<p>Add transaction types before accepting transactions.</p>
		<?php } ?>
		<p>Amounts are in CAD.</p>
		<div class="queue-grid">
			<table class="queue-table">
				<thead>
					<tr>
						<th scope="col"><input id="queue-select-all" type="checkbox" aria-label="Select all transactions" /></th>
						<th scope="col">Transaction</th>
						<th scope="col" class="queue-amount">Amount</th>
						<th scope="col">Date / time</th>
						<th scope="col">Card</th>
						<th scope="col">Transaction type</th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ($this->transactions as $transaction) { ?>
						<tr data-queue-id="<?= (int) $transaction->id; ?>">
							<td><input class="queue-row-select" type="checkbox" aria-label="<?= $this->encodeHTML('Select ' . $transaction->description); ?>" /></td>
							<td><?= $this->encodeHTML((string) $transaction->description); ?></td>
							<td class="queue-amount">$<?= number_format((float) $transaction->amount, 2); ?></td>
							<td><?= $this->encodeHTML($transaction->date ?? '—'); ?></td>
							<td><?= $this->encodeHTML($transaction->card_name . ' (' . ($transaction->card_mask ?? '—') . ')'); ?></td>
							<td>
								<select class="queue-type" aria-label="<?= $this->encodeHTML('Transaction type for ' . $transaction->description); ?>">
									<option value="">Select a type</option>
									<?php foreach ($this->transaction_types as $type) { ?>
										<option value="<?= (int) $type->id; ?>"><?= $this->encodeHTML($type->name); ?></option>
									<?php } ?>
								</select>
							</td>
						</tr>
					<?php } ?>
				</tbody>
			</table>
		</div>
		<p id="queue-status" role="status" aria-live="polite"></p>
	</form>
	<script>
		(() => {
			const form = document.getElementById('queue-form');
			const rows = [...form.querySelectorAll('[data-queue-id]')];
			const selectAll = document.getElementById('queue-select-all');
			const selectedActions = document.getElementById('queue-selected-actions');
			const acceptAll = document.getElementById('queue-accept-all');
			const selectionCount = document.getElementById('queue-selection-count');
			const status = document.getElementById('queue-status');
			let submitting = false;
			const isSelected = (row) => row.querySelector('.queue-row-select').checked;

			function updateSelection() {
				const count = rows.filter(isSelected).length;
				selectedActions.hidden = count === 0;
				acceptAll.hidden = count > 0;
				selectionCount.textContent = count ? count + ' selected' : '';
				selectAll.checked = count === rows.length;
				selectAll.indeterminate = count > 0 && count < rows.length;
			}

			selectAll.addEventListener('change', () => {
				rows.forEach((row) => { row.querySelector('.queue-row-select').checked = selectAll.checked; });
				updateSelection();
			});
			form.addEventListener('change', (event) => {
				if (event.target.matches('.queue-row-select')) updateSelection();
			});
			updateSelection();

			form.addEventListener('submit', async (event) => {
				event.preventDefault();
				if (submitting) return;
				const action = event.submitter?.value === 'reject'
					? 'reject'
					: (rows.some(isSelected) ? 'accept' : 'accept_all');
				const accepting = action !== 'reject';
				const targetRows = action === 'accept_all' ? rows : rows.filter(isSelected);
				if (!targetRows.length) return;
				rows.forEach((row) => {
					row.querySelector('.queue-type').required = accepting && targetRows.includes(row);
				});
				if (accepting && !form.reportValidity()) return;

				// Send one JSON field so large batches do not exhaust PHP's input-variable limit.
				const items = targetRows.map((row) => ({
					id: Number(row.dataset.queueId),
					category_id: row.querySelector('.queue-type').value ? Number(row.querySelector('.queue-type').value) : null,
				}));
				const buttons = [...form.querySelectorAll('button')].map((button) => [button, button.disabled]);
				submitting = true;
				buttons.forEach(([button]) => { button.disabled = true; });
				status.textContent = accepting ? 'Accepting transactions…' : 'Rejecting transactions…';
				try {
					const response = await fetch(form.action, {
						method: 'POST',
						credentials: 'same-origin',
						headers: { 'Accept': 'application/json' },
						body: new URLSearchParams({
							csrf_token: form.elements.csrf_token.value,
							action,
							items: JSON.stringify(items),
						}),
					});
					const result = await response.json().catch(() => null);
					if (!response.ok || !result?.success) {
						throw new Error(result?.error || 'Unable to review these transactions. Please try again.');
					}
					window.location.reload();
				} catch (error) {
					status.textContent = error.message || 'Unable to review these transactions. Please try again.';
				} finally {
					submitting = false;
					buttons.forEach(([button, disabled]) => { button.disabled = disabled; });
				}
			});
		})();
	</script>
<?php } ?>
