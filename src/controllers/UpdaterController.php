<?php
/**
 * @link      https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license   https://craftcms.github.io/license/
 */

namespace craft\controllers;

use Composer\IO\BufferIO;
use Craft;
use craft\base\Plugin;
use craft\errors\MigrateException;
use craft\errors\MigrationException;
use yii\base\Exception as YiiException;
use yii\web\BadRequestHttpException;
use yii\web\Response;

/**
 * UpdaterController handles the Craft/plugin update workflow.
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since  3.0
 */
class UpdaterController extends BaseUpdaterController
{
    // Constants
    // =========================================================================

    const ACTION_FORCE_UPDATE = 'force-update';
    const ACTION_BACKUP = 'backup';
    const ACTION_SERVER_CHECK = 'server-check';
    const ACTION_REVERT = 'revert';
    const ACTION_RESTORE_DB = 'restore-db';
    const ACTION_MIGRATE = 'migrate';

    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public function beforeAction($action)
    {
        if (!parent::beforeAction($action)) {
            return false;
        }

        if ($action->id !== 'index') {
            // Only users with performUpdates permission can install new versions
            if (!empty($data['install'])) {
                $this->requirePermission('performUpdates');
            }
        }

        return true;
    }

    /**
     * Forces the update even if Craft is already in Maintenance Mode.
     *
     * @return Response
     */
    public function actionForceUpdate(): Response
    {
        return $this->send($this->initialState(true));
    }

    /**
     * Backup the database.
     *
     * @return Response
     */
    public function actionBackup(): Response
    {
        try {
            $this->data['dbBackupPath'] = Craft::$app->getDb()->backup();
        } catch (\Throwable $e) {
            Craft::error('Error backing up the database: '.$e->getMessage(), __METHOD__);
            if (!empty($this->data['install'])) {
                $firstAction = $this->actionOption(Craft::t('app', 'Revert the update'), self::ACTION_REVERT);
            } else {
                $firstAction = $this->finishedState([
                    'label' => Craft::t('app', 'Abort the update'),
                    'status' => Craft::t('app', 'Update aborted.')
                ]);
            }
            return $this->send([
                'error' => Craft::t('app', 'Couldn’t backup the database. How would you like to proceed?'),
                'options' => [
                    $firstAction,
                    $this->actionOption(Craft::t('app', 'Try again'), self::ACTION_BACKUP),
                    $this->actionOption(Craft::t('app', 'Continue anyway'), self::ACTION_MIGRATE),
                ]
            ]);
        }

        return $this->sendNextAction(self::ACTION_MIGRATE);
    }

    /**
     * Restores the database.
     *
     * @return Response
     */
    public function actionRestoreDb(): Response
    {
        try {
            Craft::$app->getDb()->restore($this->data['dbBackupPath']);
        } catch (\Throwable $e) {
            Craft::error('Error restoring up the database: '.$e->getMessage(), __METHOD__);
            return $this->send([
                'error' => Craft::t('app', 'Couldn’t restore the database. How would you like to proceed?'),
                'options' => [
                    $this->actionOption(Craft::t('app', 'Try again'), self::ACTION_RESTORE_DB),
                    $this->actionOption(Craft::t('app', 'Continue anyway'), self::ACTION_MIGRATE),
                ]
            ]);
        }

        // Did we install new versions of things?
        if (!empty($this->data['install'])) {
            return $this->sendNextAction(self::ACTION_REVERT);
        }

        return $this->sendFinished([
            'status' => Craft::t('app', 'The database was restored successfully.'),
        ]);
    }

    /**
     * Reverts the site to its previous Composer package versions.
     *
     * @return Response
     */
    public function actionRevert(): Response
    {
        $io = new BufferIO();

        try {
            Craft::$app->getComposer()->install($this->data['current'], $io);
            $this->data['reverted'] = true;
        } catch (\Throwable $e) {
            Craft::error('Error reverting Composer requirements: '.$e->getMessage()."\nOutput: ".$io->getOutput(), __METHOD__);
            return $this->sendComposerError(Craft::t('app', 'Composer was unable to revert the updates.'), $e, $io);
        }

        return $this->sendNextAction(self::ACTION_COMPOSER_OPTIMIZE);
    }

    /**
     * Ensures Craft still meets the minimum system requirements
     *
     * @return Response
     */
    public function actionServerCheck(): Response
    {
        $reqCheck = new \RequirementsChecker();
        $reqCheck->checkCraft();

        $errors = [];

        if ($reqCheck->result['summary']['errors'] > 0) {
            foreach ($reqCheck->getResult()['requirements'] as $req) {
                if ($req['failed'] === true) {
                    $errors[] = $req['memo'];
                }
            }
        }

        if (!empty($errors)) {
            Craft::warning("The server doesn't meet Craft's new requirements:\n - ".implode("\n - ", $errors), __METHOD__);
            return $this->send([
                'error' => Craft::t('app', 'The server doesn’t meet Craft’s new requirements:').' '.implode(', ', $errors),
                'options' => [
                    $this->actionOption(Craft::t('app', 'Revert update'), self::ACTION_REVERT),
                    $this->actionOption(Craft::t('app', 'Check again'), self::ACTION_SERVER_CHECK),
                ]
            ]);
        }

        // Are there any migrations to run?
        $installedHandles = array_keys($this->data['install']);
        $pendingHandles = Craft::$app->getUpdates()->getPendingMigrationHandles();
        if (!empty(array_intersect($pendingHandles, $installedHandles))) {
            $backup = Craft::$app->getConfig()->getGeneral()->getBackupOnUpdate();
            return $this->sendNextAction($backup ? self::ACTION_BACKUP : self::ACTION_MIGRATE);
        }

        // Nope - we're done!
        return $this->sendFinished();
    }

    /**
     * Runs pending migrations.
     *
     * @return Response
     */
    public function actionMigrate(): Response
    {
        if (!empty($this->data['install'])) {
            $handles = array_keys($this->data['install']);
        } else {
            $handles = array_merge($this->data['migrate']);
        }

        try {
            Craft::$app->getUpdates()->runMigrations($handles);
        } catch (MigrateException $e) {
            $ownerName = $e->ownerName;
            $ownerHandle = $e->ownerHandle;
            /** @var \Throwable $e */
            $e = $e->getPrevious();

            if ($e instanceof MigrationException) {
                /** @var \Throwable|null $previous */
                $previous = $e->getPrevious();
                $migration = $e->migration;
                $output = $e->output;
                $error = get_class($migration).' migration failed'.($previous ? ': '.$previous->getMessage() : '.');
                $e = $previous ?? $e;
            } else {
                $migration = $output = null;
                $error = 'Migration failed: '.$e->getMessage();
            }

            Craft::error($error, __METHOD__);

            $options = [];

            // Do we have a database backup to restore?
            if (!empty($this->data['dbBackupPath'])) {
                if (!empty($this->data['install'])) {
                    $restoreLabel = Craft::t('app', 'Revert update');
                } else {
                    $restoreLabel = Craft::t('app', 'Restore database');
                }
                $options[] = $this->actionOption($restoreLabel, self::ACTION_RESTORE_DB);
            }

            if ($ownerHandle !== 'craft' && ($plugin = Craft::$app->getPlugins()->getPlugin($ownerHandle)) !== null) {
                /** @var Plugin $plugin */
                $email = $plugin->developerEmail;
            }
            $email = $email ?? 'support@craftcms.com';

            $options[] = [
                'label' => Craft::t('app', 'Send for help'),
                'submit' => true,
                'email' => $email,
                'subject' => $ownerName.' update failure',
            ];

            $eName = $e instanceof YiiException ? $e->getName() : get_class($e);

            return $this->send([
                'error' => Craft::t('app', 'One of {name}’s migrations failed.', ['name' => $ownerName]),
                'errorDetails' => $eName.': '.$e->getMessage().
                    ($migration ? "\n\nMigration: ".get_class($migration) : '').
                    ($output ? "\n\nOutput:\n\n".$output : ''),
                'options' => $options,
            ]);
        }

        return $this->sendFinished();
    }

    // Protected Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    protected function pageTitle(): string
    {
        return Craft::t('app', 'Updater');
    }

    /**
     * @inheritdoc
     */
    protected function initialData(): array
    {
        $request = Craft::$app->getRequest();

        // Set the things to install, if any
        if (($install = $request->getBodyParam('install')) !== null) {
            $data = [
                'install' => $this->_parseInstallParam($install),
                'current' => [],
                'requirements' => [],
                'reverted' => false,
            ];

            // Convert update handles to Composer package names, and capture current versions
            foreach ($data['install'] as $handle => $version) {
                if ($handle === 'craft') {
                    $packageName = 'craftcms/cms';
                    $current = Craft::$app->getVersion();
                } else {
                    /** @var Plugin $plugin */
                    $plugin = Craft::$app->getPlugins()->getPlugin($handle);
                    $packageName = $plugin->packageName;
                    $current = $plugin->getVersion();
                }
                $data['current'][$packageName] = $current;
                $data['requirements'][$packageName] = $version;
            }
        } else {
            // Figure out what needs to be updated, if any
            $data = [
                'migrate' => Craft::$app->getUpdates()->getPendingMigrationHandles(),
            ];
        }

        // Set the return URL, if any
        if (($returnUrl = $request->getBodyParam('return')) !== null) {
            $data['returnUrl'] = strip_tags($returnUrl);
        }

        return $data;
    }

    /**
     * Returns the initial state for the updater JS.
     *
     * @param bool $force Whether to go through with the update even if Maintenance Mode is enabled
     *
     * @return array
     */
    protected function initialState(bool $force = false): array
    {
        // Is there anything to install/update?
        if (empty($this->data['install']) && empty($this->data['migrate'])) {
            return $this->finishedState([
                'status' => Craft::t('app', 'Nothing to update.')
            ]);
        }

        // Is Craft already in Maintenance Mode?
        if (!$force && Craft::$app->getIsInMaintenanceMode()) {
            // Bail if Craft is already in maintenance mode
            return [
                'error' => str_replace('<br>', "\n\n", Craft::t('app', 'It looks like someone is currently performing a system update.<br>Only continue if you’re sure that’s not the case.')),
                'options' => [
                    $this->actionOption(Craft::t('app', 'Continue'), self::ACTION_FORCE_UPDATE, ['submit' => true]),
                ]
            ];
        }

        // If there's anything to install, make sure we can find composer.json
        if (!empty($this->data['install']) && !$this->ensureComposerJson()) {
            return $this->noComposerJsonState();
        }

        // Enable maintenance mode
        Craft::$app->enableMaintenanceMode();

        if (!empty($this->data['install'])) {
            $nextAction = self::ACTION_COMPOSER_INSTALL;
        } else {
            $backup = Craft::$app->getConfig()->getGeneral()->getBackupOnUpdate();
            $nextAction = $backup ? self::ACTION_BACKUP : self::ACTION_MIGRATE;
        }

        return $this->actionState($nextAction);
    }

    /**
     * @inheritdoc
     */
    protected function postComposerOptimizeState(): array
    {
        // Was this after a revert?
        if ($this->data['reverted']) {
            return $this->actionState(self::ACTION_FINISH, [
                'status' => Craft::t('app', 'The update was reverted successfully.'),
            ]);
        }

        return $this->actionState(self::ACTION_SERVER_CHECK);
    }

    /**
     * Returns the return URL that should be passed with a finished state.
     *
     * @return string
     */
    protected function returnUrl(): string
    {
        return $this->data['returnUrl'] ?? Craft::$app->getConfig()->getGeneral()->getPostCpLoginRedirect();
    }

    /**
     * @inheritdoc
     */
    protected function actionStatus(string $action): string
    {
        switch ($action) {
            case self::ACTION_FORCE_UPDATE:
                return Craft::t('app', 'Updating…');
            case self::ACTION_BACKUP:
                return Craft::t('app', 'Backing-up database…');
            case self::ACTION_RESTORE_DB:
                return Craft::t('app', 'Restoring database…');
            case self::ACTION_MIGRATE:
                return Craft::t('app', 'Updating database…');
            case self::ACTION_REVERT:
                return Craft::t('app', 'Reverting update (this may take a minute)…');
            case self::ACTION_SERVER_CHECK:
                return Craft::t('app', 'Checking server requirements…');
            default:
                return parent::actionStatus($action);
        }
    }

    /**
     * @inheritdoc
     */
    protected function sendFinished(array $state = []): Response
    {
        // Disable maintenance mode
        Craft::$app->disableMaintenanceMode();

        return parent::sendFinished($state);
    }

    // Private Methods
    // =========================================================================

    /**
     * Parses the 'install` param and returns handle => version pairs.
     *
     * @param array $installParam
     *
     * @return array
     * @throws BadRequestHttpException
     */
    private function _parseInstallParam(array $installParam): array
    {
        $install = [];

        foreach ($installParam as $handle => $version) {
            $handle = strip_tags($handle);
            $version = strip_tags($version);
            if ($this->_canUpdate($handle, $version)) {
                $install[$handle] = $version;
            }
        }

        return $install;
    }

    /**
     * Returns whether Craft/a plugin can be updated to a given version.
     *
     * @param string $handle
     * @param string $toVersion
     *
     * @return bool
     * @throws BadRequestHttpException if the handle is invalid
     */
    private function _canUpdate(string $handle, string $toVersion): bool
    {
        if ($handle === 'craft') {
            $fromVersion = Craft::$app->getVersion();
        } else {
            /** @var Plugin|null $plugin */
            if (($plugin = Craft::$app->getPlugins()->getPlugin($handle)) === null) {
                throw new BadRequestHttpException('Invalid update handle: '.$handle);
            }
            $fromVersion = $plugin->getVersion();
        }

        return version_compare($toVersion, $fromVersion, '>');
    }
}
