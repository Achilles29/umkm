<?php
defined('BASEPATH') OR exit('No direct script access allowed');

if (!function_exists('staff_scope_districts'))
{
    function staff_scope_districts($db,$user)
    {
        if (in_array($user['role_code'],array('superadmin','admin_dinas'),TRUE)) return NULL;
        if ($user['role_code']!=='admin_kecamatan') return FALSE;
        $rows=$db->query("SELECT DISTINCT dr.district_id FROM iam_district_roles dr
            JOIN iam_roles r ON r.id=dr.role_id WHERE dr.user_id=? AND r.code='admin_kecamatan'",
            array((int)$user['id']))->result_array();
        return array_map('intval',array_column($rows,'district_id'));
    }
}

if (!function_exists('staff_scope_has_district'))
{
    function staff_scope_has_district($scope,$district_id)
    {
        return $scope===NULL || (is_array($scope) && in_array((int)$district_id,$scope,TRUE));
    }
}

if (!function_exists('staff_scope_business'))
{
    function staff_scope_business($db,$user,$business_id)
    {
        $scope=staff_scope_districts($db,$user);
        if ($scope===FALSE || !$business_id) return NULL;
        $row=$db->query('SELECT id,district_id FROM umk_businesses
            WHERE id=? AND is_demo=0 AND canonical_business_id IS NULL LIMIT 1',array((int)$business_id))->row_array();
        return $row && staff_scope_has_district($scope,$row['district_id']) ? $row : NULL;
    }
}

if (!function_exists('staff_scope_store'))
{
    function staff_scope_store($db,$user,$store_id)
    {
        $scope=staff_scope_districts($db,$user);
        if ($scope===FALSE || !$store_id) return NULL;
        $row=$db->query('SELECT s.id,s.business_id,b.district_id,b.is_demo,b.canonical_business_id
            FROM tko_stores s JOIN umk_businesses b ON b.id=s.business_id WHERE s.id=? LIMIT 1',array((int)$store_id))->row_array();
        if (!$row) return NULL;
        if ($scope===NULL) return $row;
        return !$row['is_demo'] && $row['canonical_business_id']===NULL &&
            staff_scope_has_district($scope,$row['district_id']) ? $row : NULL;
    }
}

if (!function_exists('staff_scope_person'))
{
    function staff_scope_person($db,$user,$person_id,$edit=FALSE)
    {
        $scope=staff_scope_districts($db,$user);
        if ($scope===FALSE || !$person_id) return FALSE;
        $person=$db->query('SELECT id,registration_district_id FROM umk_people WHERE id=? LIMIT 1',array((int)$person_id))->row_array();
        if (!$person) return FALSE;
        if ($scope===NULL) return TRUE;
        if (!$scope) return FALSE;
        $ids=implode(',',array_map('intval',$scope));
        $activity=$db->query('SELECT COUNT(*) total,
            SUM(CASE WHEN b.district_id IN ('.$ids.') THEN 1 ELSE 0 END) local_count
            FROM umk_business_owners o JOIN umk_businesses b ON b.id=o.business_id
            WHERE o.person_id=? AND o.is_primary=1 AND b.is_demo=0 AND b.canonical_business_id IS NULL',array((int)$person_id))->row_array();
        $registered=$person['registration_district_id']!==NULL && in_array((int)$person['registration_district_id'],$scope,TRUE);
        if (!$edit) return ($registered && (int)$activity['total']===0) || (int)$activity['local_count']>0;
        if ($person['registration_district_id']!==NULL && !$registered) return FALSE;
        return ($registered || (int)$activity['local_count']>0) &&
            (int)$activity['total']===(int)$activity['local_count'];
    }
}
