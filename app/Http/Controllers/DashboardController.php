<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Gridphp;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    /**
     * Show the profile for a given user.
     *
     * @return \Illuminate\View\View
     */
    public function view()
    {
        if (Auth::check()) { // Check if the user is authenticated

            $g = Gridphp::get();

            $opt = array();
            
            $opt["caption"] = "Clients";
            $opt["height"] = "400";
            $opt["import"]["allowreplace"] = true;
            $opt["import"]["match"] = 95;
            $opt["altRows"] = true;
            $opt["hotkeys"] = true;

            $g->set_options($opt);
            $g->set_actions(array(
                "add" => true,
                "edit" => true,
                "delete" => true,
                "rowactions" => true,
                "search" => "advance",
                "import"=>true,
                "export_excel"=>true,
                "export_csv"=>true,
                "export_pdf"=>true
            ));

            $g->table = "users";
            
            $g->select_command = "SELECT id, name, role, email, address, license_type, sub_domain, created_at FROM users";

            // Id col
            $col = array();
            $col["title"] = "Id";
            $col["name"] = "id";
            $col["editable"] = false;
            $cols[] = $col;
            
            // Name col
            $col = array();
            $col["title"] = "Name";
            $col["name"] = "name";
            $col["editable"] = true;
            $cols[] = $col;

            // Role col
            $col = array();
            $col["title"] = "Role";
            $col["name"] = "role";
            $col["search"] = true;
            $col["editable"] = true;
            $col["edittype"] = "select";
            $str = $g->get_dropdown_values("SELECT DISTINCT role as k, role as v FROM users");
            $col["editoptions"] = array("value"=>":;".$str);
            $col["stype"] = "select-multiple";
            $col["searchoptions"]["value"] = $str;
            // $col["editoptions"]["dataInit"] = "function(){ setTimeout(function(){ $('select[name=role]').select2({width:'80%', dropdownCssClass: 'ui-widget ui-jqdialog'}); },200); }";
            $cols[] = $col;

            // Email col
            $col = array();
            $col["title"] = "Email";
            $col["name"] = "email";
            $col["editable"] = true;
            $cols[] = $col;
            
            // Address col
            $col = array();
            $col["title"] = "Address";
            $col["name"] = "address";
            $col["editable"] = true;
            $cols[] = $col;

            // License_type col
            $col = array();
            $col["title"] = "License type";
            $col["name"] = "license_type";
            $col["search"] = true;
            $col["editable"] = true;
            $col["edittype"] = "select";
            $str = $g->get_dropdown_values("SELECT DISTINCT license_type as k, license_type as v FROM users");
            $col["editoptions"] = array("value"=>":;".$str);
            $col["stype"] = "select-multiple";
            $col["searchoptions"]["value"] = $str;
            $cols[] = $col;
            
            // Sub_domain col
            $col = array();
            $col["title"] = "Sub domain";
            $col["name"] = "sub_domain";
            $col["editable"] = true;
            $cols[] = $col;
            
            // Created_at col
            $col = array();
            $col["title"] = "Created at";
            $col["name"] = "created_at";
            $col["editable"] = true;
            $col["formatter"] = "datetime";
            $col["search"] = false;
            $cols[] = $col;

            $g->set_columns($cols);

            $out = $g->render("list1");
            
            return view('dashboard', [
                'grid' => $out,
                'showWelcomeModal' => session('show_welcome_modal', false)
            ]);
        }

        // Redirect or show a different view if not authenticated
        return redirect('/login');
    }
}