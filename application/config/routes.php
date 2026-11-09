<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/*
| -------------------------------------------------------------------------
| URI ROUTING
| -------------------------------------------------------------------------
| This file lets you re-map URI requests to specific controller functions.
|
| Typically there is a one-to-one relationship between a URL string
| and its corresponding controller class/method. The segments in a
| URL normally follow this pattern:
|
|	example.com/class/method/id/
|
| In some instances, however, you may want to remap this relationship
| so that a different class/function is called than the one
| corresponding to the URL.
|
| Please see the user guide for complete details:
|
|	https://codeigniter.com/userguide3/general/routing.html
|
| -------------------------------------------------------------------------
| RESERVED ROUTES
| -------------------------------------------------------------------------
|
| There are three reserved routes:
|
|	$route['default_controller'] = 'welcome';
|
| This route indicates which controller class should be loaded if the
| URI contains no data. In the above example, the "welcome" class
| would be loaded.
|
|	$route['404_override'] = 'errors/page_missing';
|
| This route will tell the Router which controller/method to use if those
| provided in the URL cannot be matched to a valid route.
|
|	$route['translate_uri_dashes'] = FALSE;
|
| This is not exactly a route, but allows you to automatically route
| controller and method names that contain dashes. '-' isn't a valid
| class or method name character, so it requires translation.
| When you set this option to TRUE, it will replace ALL dashes in the
| controller and method URI segments.
|
| Examples:	my-controller/index	-> my_controller/index
|		my-controller/my-method	-> my_controller/my_method
*/
$route['default_controller'] = 'home';
$route['masuk'] = 'market/signin';
$route['masuk/nama'] = 'market/name';
$route['jelajah'] = 'market/index';
$route['daftar-toko'] = 'market/stores';
$route['peta'] = 'market/map';
$route['toko/(:any)'] = 'market/store/$1';
$route['produk/(:num)'] = 'market/product/$1';
$route['keranjang'] = 'market/cart';
$route['keranjang/tambah'] = 'market/add_to_cart';
$route['keranjang/ubah'] = 'market/change_cart';
$route['kontak/produk'] = 'market/contact_product';
$route['kontak/toko'] = 'market/contact_store';
$route['kontak/keranjang/(:num)'] = 'market/contact_cart/$1';
$route['kontak/(:any)/buka'] = 'market/open_contact/$1';
$route['kontak/(:any)'] = 'market/contact/$1';
$route['akun'] = 'market/account';
$route['keluar'] = 'market/logout';
$route['q/(:any)'] = 'qr/visit/$1';
$route['kegiatan/(:any)/daftar'] = 'events/register/$1';
$route['kegiatan/(:any)'] = 'events/detail/$1';
$route['kegiatan'] = 'events/index';
$route['pengelola/masuk'] = 'staff/login';
$route['pengelola/pulihkan-akun/kirim'] = 'recovery/submit';
$route['pengelola/pulihkan-akun'] = 'recovery/form';
$route['pengelola/pemulihan-akun/(:num)/putusan'] = 'recovery/decide/$1';
$route['pengelola/pemulihan-akun/(:num)/bukti'] = 'recovery/evidence/$1';
$route['pengelola/pemulihan-akun'] = 'recovery/queue';
$route['pengelola/klaim-usaha/(:num)/putusan'] = 'claims/decide/$1';
$route['pengelola/klaim-usaha/(:num)/bukti'] = 'claims/evidence/$1';
$route['pengelola/klaim-usaha/kirim'] = 'claims/submit';
$route['pengelola/klaim-usaha'] = 'claims/index';
$route['pengelola/tinjauan-produk/(:num)/putusan'] = 'catalog/review/$1';
$route['pengelola/tinjauan-produk'] = 'catalog/reviews';
$route['pengelola/pengajuan-lokasi/(:num)/putusan'] = 'location_requests/decide/$1';
$route['pengelola/pengajuan-lokasi/kirim'] = 'location_requests/submit';
$route['pengelola/pengajuan-lokasi'] = 'location_requests/index';
$route['pengelola/layanan/(:num)/tindak-lanjut'] = 'service_tickets/update/$1';
$route['pengelola/layanan/(:num)'] = 'service_tickets/detail/$1';
$route['pengelola/layanan/baru'] = 'service_tickets/create';
$route['pengelola/layanan'] = 'service_tickets/index';
$route['pengelola/qr/(:num)/status'] = 'qr_admin/toggle/$1';
$route['pengelola/qr/buat'] = 'qr_admin/create';
$route['pengelola/qr'] = 'qr_admin/index';
$route['pengelola/kegiatan/pendaftaran/(:num)/status'] = 'events/admin_status/$1';
$route['pengelola/kegiatan/(:num)/peserta'] = 'events/admin_participants/$1';
$route['pengelola/kegiatan/(:num)/studio'] = 'events/admin_studio/$1';
$route['pengelola/kegiatan/(:num)/ubah'] = 'events/admin_edit/$1';
$route['pengelola/kegiatan/baru'] = 'events/admin_edit';
$route['pengelola/kegiatan'] = 'events/admin_index';
$route['pengelola/produk/(:num)'] = 'catalog/edit/$1';
$route['pengelola/produk'] = 'catalog/index';
$route['pengelola/produk-baru'] = 'catalog/create';
$route['pengelola/toko/baru'] = 'admin_create/store';
$route['pengelola/toko/(:num)/aktifkan'] = 'admin_create/activate_store/$1';
$route['pengelola/akses-toko'] = 'catalog/access';
$route['pengelola/kategori/(:num)/putusan'] = 'categories/decide/$1';
$route['pengelola/kategori/ajukan'] = 'categories/submit';
$route['pengelola/kategori/atur'] = 'categories/assign';
$route['pengelola/kategori'] = 'categories/index';
$route['pengelola/inbox/(:num)/buka'] = 'inbox/open_item/$1';
$route['pengelola/inbox/baca-semua'] = 'inbox/read_all';
$route['pengelola/inbox'] = 'inbox/index';
$route['pengelola/anggota/(:num)'] = 'members/detail/$1';
$route['pengelola/anggota'] = 'members/index';
$route['pengelola/minat/(:num)'] = 'inbox/contact/$1';
$route['pengelola/toko/(:num)'] = 'catalog/store_profile/$1';
$route['pengelola/toko'] = 'catalog/stores';
$route['pengelola/usaha-saya/kirim'] = 'imported_profile/submit';
$route['pengelola/usaha-saya/kata-sandi'] = 'imported_profile/password';
$route['pengelola/usaha-saya'] = 'imported_profile/index';
$route['pengelola/pembaruan-profil/(:num)/putusan'] = 'imported_profile/decision/$1';
$route['pengelola/master/kategori/simpan'] = 'master_data/save_category';
$route['pengelola/master/kategori'] = 'master_data/categories';
$route['pengelola/master/skala'] = 'master_data/scales';
$route['pengelola/master/kbli/versi'] = 'master_data/save_kbli_version';
$route['pengelola/master/kbli/impor'] = 'master_data/import_kbli';
$route['pengelola/master/kbli'] = 'master_data/kbli';
$route['pengelola/master/wilayah'] = 'master_data/territory';
$route['pengelola/master'] = 'master_data/index';
$route['pengelola/peta-umkm'] = 'registry_map/index';
$route['pengelola'] = 'staff/dashboard';
$route['pengelola/wilayah'] = 'staff/area';
$route['pengelola/banner'] = 'staff/banner';
$route['pengelola/kontak'] = 'staff/contact_settings';
$route['pengelola/registri/(:num)/catatan'] = 'staff/business_note/$1';
$route['pengelola/registri/(:num)/koordinat'] = 'staff/business_location/$1';
$route['pengelola/registri/(:num)/cocokkan/(:num)'] = 'staff/person_match/$1/$2';
$route['pengelola/registri/(:num)/profil'] = 'registry_editor/profile/$1';
$route['pengelola/registri/(:num)/pelaku'] = 'registry_editor/person/$1';
$route['pengelola/registri/(:num)/pelaku-baru'] = 'registry_editor/create_person/$1';
$route['pengelola/registri/(:num)/hubungkan-pelaku'] = 'registry_editor/link_person/$1';
$route['pengelola/registri/(:num)/kbli'] = 'registry_editor/kbli/$1';
$route['pengelola/registri/(:num)/komoditas'] = 'registry_editor/commodity/$1';
$route['pengelola/registri/(:num)/komoditas/(:num)/ubah'] = 'registry_editor/update_commodity/$1/$2';
$route['pengelola/registri/(:num)/komoditas/(:num)/hapus'] = 'registry_editor/delete_commodity/$1/$2';
$route['pengelola/registri/(:num)/nib'] = 'registry_editor/nib/$1';
$route['pengelola/registri/(:num)/dokumen-nib'] = 'registry_editor/upload_nib_document/$1';
$route['pengelola/registri/(:num)/dokumen-nib/(:num)'] = 'registry_editor/download_nib_document/$1/$2';
$route['pengelola/registri/(:num)'] = 'staff/business/$1';
$route['pengelola/registri/baru'] = 'admin_create/business';
$route['pengelola/registri'] = 'staff/registry';
$route['pengelola/pelaku/(:num)'] = 'staff/person/$1';
$route['pengelola/pelaku/(:num)/ubah'] = 'admin_create/edit_person/$1';
$route['pengelola/pelaku/baru'] = 'admin_create/person';
$route['pengelola/pelaku'] = 'staff/people';
$route['pengelola/lokasi/(:num)'] = 'staff/location/$1';
$route['pengelola/lokasi'] = 'staff/locations';
$route['pengelola/keluar'] = 'staff/logout';
$route['404_override'] = '';
$route['translate_uri_dashes'] = FALSE;
