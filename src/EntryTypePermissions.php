<?php

namespace brikdigital\entrytypepermissions;

use Craft;
use craft\base\Event;
use craft\base\Plugin;
use craft\events\RegisterUserPermissionsEvent;
use craft\services\UserPermissions;

class EntryTypePermissions extends Plugin
{
    public static EntryTypePermissions $plugin;
    public string $schemaVersion = "5.0.0";

    public function init()
    {
        parent::init();

        self::$plugin = $this;

        if (Craft::$app->getRequest()->getIsCpRequest()) {
            $this->registerUserPermissions();
        }
    }

    public function registerUserPermissions(): void
    {
        Event::on(UserPermissions::class, UserPermissions::EVENT_REGISTER_PERMISSIONS,
            function (RegisterUserPermissionsEvent $event) {
                $sections = Craft::$app->entries->getAllSections();

                $sectionEntryTypes = [];
                foreach ($sections as $section) {
                    $sectionEntryTypes = array_merge($sectionEntryTypes, $section->entryTypes);
                }
                $sectionEntryTypes = array_values(array_unique($sectionEntryTypes));

                $perms = [];
                array_map(function ($entryType) use (&$perms) {
                    $perms["createEntryType:$entryType->uid"] = [
                        "label" => $entryType->name,
                    ];
                }, $sectionEntryTypes);

                $event->permissions[] = [
                    'heading' => 'Entry Type Permissions',
                    'permissions' => $perms
                ];
            }
        );
    }
}
