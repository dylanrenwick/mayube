<h3>Add Card</h3>

<?php $this->renderFeedbackMessages(); ?>

<form method="post" action="<?= $this->encodeHTML(Config::get('URL') . 'cards/create'); ?>">
    <label for="card-provider">Provider</label><br>
    <select id="card-provider" name="provider" required>
        <option value="" disabled selected>Select a provider</option>
        <?php foreach ($this->providers as $provider) { ?>
            <option value="<?= $this->encodeHTML($provider); ?>"><?= $this->encodeHTML($provider); ?></option>
        <?php } ?>
    </select>
    <br>

    <label for="card-name">Card name</label><br>
    <input type="text" id="card-name" name="name" placeholder="Card name" required />
    <br>

    <input type="hidden" name="csrf_token" value="<?= $this->encodeHTML(Csrf::makeToken()); ?>" />
    <?php if (empty($this->providers)) { ?>
        <p>No card providers are currently available.</p>
    <?php } ?>
    <input type="submit" value="Add Card" <?= empty($this->providers) ? 'disabled' : ''; ?> />
</form>
