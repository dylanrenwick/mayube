<p id="plaid-status" role="status" aria-live="polite">Starting Plaid Link…</p>
<button type="button" id="plaid-retry" hidden>Retry loading accounts</button>
<form id="plaid-account-form" hidden>
	<label for="plaid-account">Choose the account for this card</label>
	<select id="plaid-account" name="account_id" required></select>
	<button type="submit">Create card</button>
</form>
<script src="https://cdn.plaid.com/link/v2/stable/link-initialize.js"></script>
<script>
	(async () => {
		const status = document.getElementById('plaid-status');
		const baseUrl = <?= json_encode(Config::get('URL'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
		const csrfToken = <?= json_encode(Csrf::makeToken(), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
		const accountForm = document.getElementById('plaid-account-form');
		const accountSelect = document.getElementById('plaid-account');
		const retryButton = document.getElementById('plaid-retry');
		let publicToken = null;

		async function requestJSON(path, options = {}) {
			const response = await fetch(baseUrl + path, {
				credentials: 'same-origin',
				headers: { 'Accept': 'application/json' },
				...options,
			});
			const data = await response.json().catch(() => null);
			if (!response.ok || !data || data.error) {
				throw new Error(data?.error || 'Unable to connect your card. Please try again.');
			}
			return data;
		}

		async function loadAccounts() {
			retryButton.hidden = true;
			retryButton.disabled = true;
			accountForm.hidden = true;
			status.textContent = 'Loading your accounts…';
			try {
				const data = await requestJSON('plaid/exchange', {
					method: 'POST',
					body: new URLSearchParams({ public_token: publicToken, csrf_token: csrfToken }),
				});
				if (!Array.isArray(data.accounts) || data.accounts.length === 0) {
					throw new Error('No accounts are available for this connection.');
				}
				accountSelect.replaceChildren();
				for (const account of data.accounts) {
					const option = document.createElement('option');
					option.value = account.id;
					option.textContent = [account.name, account.subtype || account.type, account.mask ? '•••• ' + account.mask : '']
						.filter(Boolean).join(' — ');
					accountSelect.appendChild(option);
				}
				accountForm.hidden = false;
				status.textContent = 'Choose the account to track with this card.';
			} catch (error) {
				status.textContent = error.message || 'Unable to load your accounts. Please try again.';
				retryButton.hidden = false;
			} finally {
				retryButton.disabled = false;
			}
		}

		retryButton.addEventListener('click', loadAccounts);
		accountForm.addEventListener('submit', async (event) => {
			event.preventDefault();
			const button = accountForm.querySelector('button');
			button.disabled = true;
			status.textContent = 'Creating your card…';
			try {
				await requestJSON('plaid/selectaccount', {
					method: 'POST',
					body: new URLSearchParams({ account_id: accountSelect.value, csrf_token: csrfToken }),
				});
				window.location.assign(baseUrl + 'cards/create');
			} catch (error) {
				status.textContent = error.message || 'Unable to select your account. Please try again.';
				button.disabled = false;
			}
		});

		try {
			const { link_token } = await requestJSON('plaid/getlink');
			if (typeof link_token !== 'string' || !link_token || !window.Plaid) {
				throw new Error('Unable to start Plaid Link. Please reload and try again.');
			}

			const handler = Plaid.create({
				token: link_token,
				onSuccess: async (token) => {
					if (typeof token !== 'string' || !token) {
						status.textContent = 'Plaid did not return a public token. Please try again.';
						return;
					}
					publicToken = token;
					await loadAccounts();
				},
				onExit: (error) => {
					status.textContent = error
						? 'Plaid Link encountered an error. Please reload and try again.'
						: 'Card connection cancelled. Reload this page to try again.';
				},
			});
			status.textContent = 'Complete the steps in Plaid Link to connect your card.';
			handler.open();
		} catch (error) {
			status.textContent = error.message || 'Unable to start Plaid Link. Please try again.';
		}
	})();
</script>
