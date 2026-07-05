<?php

namespace App\Enums;

/**
 * Single source of truth untuk nama role yang tersimpan di database.
 *
 * Gunakan enum ini setiap kali perlu menyebut nama role (assignRole, syncRoles,
 * query ke tabel roles, dll). Untuk pengecekan otorisasi, lebih baik gunakan
 * permission (mis. $user->can('bypass_data_scope')) agar tidak bergantung pada
 * nama role.
 */
enum EnumRole: string
{
    // WAJIB: role admin tertinggi yang bisa mengakses semua fitur.
    case SuperAdmin = 'Super Admin';

    // CONTOH role tambahan — sesuaikan / hapus sesuai kebutuhan project:
    // case GeneralAdmin = 'General Admin';
    // case Operator = 'Operator';
    // case Member = 'Member';
}
