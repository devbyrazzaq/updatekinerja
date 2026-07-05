<?php

namespace App\Enums;

/**
 * Permission lintas-fitur (tidak terikat pada satu resource) yang dipakai untuk
 * otorisasi berbasis kemampuan, bukan berbasis nama role.
 */
enum EnumPermission: string
{
    /**
     * Boleh melihat seluruh data tanpa pembatasan kepemilikan / penugasan.
     * Diberikan ke role admin (mis. Super Admin) lewat RoleSeeder.
     */
    case BypassDataScope = 'bypass_data_scope';
}
