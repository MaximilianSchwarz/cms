<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\services;

use Craft;
use craft\helpers\DateTimeHelper;
use craft\helpers\Db;
use DateInterval;
use yii\base\Component;

/**
 * Garbage collection service.
 * An instance of the GC service is globally accessible in Craft via [[\craft\base\ApplicationTrait::getGc()|`Craft::$app->gc`]].
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 3.1
 */
class Gc extends Component
{
    /**
     * @event Event The event that is triggered when running garbage collection.
     */
    const EVENT_RUN = 'run';

    /**
     * @var int the probability (parts per million) that garbage collection (GC) should be performed
     * on a request. Defaults to 10, meaning 0.001% chance.
     *
     * This number should be between 0 and 1000000. A value 0 means no GC will be performed at all unless forced.
     */
    public $probability = 10;

    /**
     * Possibly runs garbage collection.
     *
     * @param bool $force Whether garbage collection should be forced. If left as `false`, then
     * garbage collection will only run if a random condition passes, factoring in [[probability]].
     */
    public function run(bool $force = false)
    {
        if (!$force && mt_rand(0, 1000000) >= $this->probability) {
            return;
        }

        Craft::$app->getUsers()->purgeExpiredPendingUsers();
        $this->_deleteStaleSessions();

        // Fire a 'run' event
        if ($this->hasEventHandlers(self::EVENT_RUN)) {
            $this->trigger(self::EVENT_RUN);
        }
    }

    /**
     * Deletes any session rows that have gone stale.
     */
    private function _deleteStaleSessions()
    {
        $interval = new DateInterval('P3M');
        $expire = DateTimeHelper::currentUTCDateTime();
        $pastTime = $expire->sub($interval);

        Craft::$app->getDb()->createCommand()
            ->delete('{{%sessions}}', ['<', 'dateUpdated', Db::prepareDateForDb($pastTime)])
            ->execute();
    }
}
