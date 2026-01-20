<?php

namespace App\Http\Controllers;

use App\Models\User;
use Faker\Core\Number;
use App\Models\department;
use Illuminate\Http\Request;
use App\Http\Resources\DepartmentResource;
use App\Http\Requests\StoredepartementRequest;
use App\Http\Requests\UpdatedepartementRequest;
use App\Models\affectation_users;
use App\Models\requests;
use Illuminate\Support\Facades\DB;

class DepartementController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        try {
            // Récupérer l'enterprise_id depuis la query string
            $enterprise_id = $request->query('enterprise_id');

            if (!$enterprise_id) {
                return $this->errorResponse('enterprise_id est requis.');
            }

            $departments = department::where('enterprise_id', $enterprise_id)->get();

            // Transformer les données avec show()
            $listdata = $departments->map(function ($item) {
                return $this->show($item);
            });

            return $this->successResponse('success', $listdata);

        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage());
        }
    }



    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
     public function store(Request $request)
    {
        DB::beginTransaction();
        try {
            // Vérifier doublon sur department_name + enterprise_id
            $exists = department::where('department_name', $request->department_name)
                ->where('enterprise_id', $request->enterprise_id)
                ->exists();

            if ($exists) {
                return $this->errorResponse('error',409);
            }

            // Création du département
            $newdepart = department::create($request->all());

            // Si des sous-départements sont envoyés
            if ($request->has('subdeparts') && is_array($request->subdeparts)) {
                foreach ($request->subdeparts as $depart) {
                    $depart['header_depart'] = $newdepart->id;
                    $departupdated = department::find($depart['id']);
                    if ($departupdated) {
                        $departupdated->update($depart);
                    }
                }
            }

            DB::commit();
            return $this->successResponse('success',$this->show($newdepart));

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse('error');
        }
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\departement  $departement
     * @return \Illuminate\Http\Response
     */
    public function show(department $departement)
    {
        $depart=$departement;
        $depart['nbragents']=$this->nbrusersdepart($departement->id);
        return $depart;
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\departement  $departement
     * @return \Illuminate\Http\Response
     */
    public function edit(department $departement)
    {
        //
    }
    private function nbrusersdepart($id){
        return affectation_users::where('department_id','=',$id)
        ->count();
    }
    private function nbrRequestsdepart($id){
        return requests::where('department_id','=',$id)
        ->count();
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \App\Http\Requests\UpdatedepartementRequest  $request
     * @param  \App\Models\departement  $departement
     * @return \Illuminate\Http\Response
     */
    public function update(UpdatedepartementRequest $request, department $departement)
    {
        $element = department::find($departement);
        return $this->show($element->update($request->all()));
    }

    public function update2(Request $request, $id){
        $depart=department::find($id);
        $exists = department::where('department_name', $request->department_name)
            ->where('enterprise_id', $request->enterprise_id)
            ->exists();

        if ($exists) {
            return $this->errorResponse('error',409);
        }
        $depart->update($request->all());
        return $this->show($depart);
    }

    //find one by id 
    public function findbyid($id){
        $element= department::find($id);
        
        return $this->show($element);
    }
    
    //find users affected
    public function findusers($id)
    {
        return User::leftjoin('affectation_users as A', 'users.id','=','A.user_id')
        ->where('A.department_id','=',$id)
        ->get(['users.*', 'A.level','A.id as affectation_id']);
    }

    public function findsubdeparts($id){
        return department::where('header_depart','=',$id)
        ->get();
    }
    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\departement  $departement
     * @return \Illuminate\Http\Response
     */
    public function destroy(department $departement)
    {
        return department::destroy($departement);
    }
    
    public function destroy2($id)
    {
        if ($this->nbrusersdepart($id) > 0 || $this->nbrRequestsdepart($id) > 0) {
            return $this->errorResponse('Impossible de supprimer ce département car il contient les opérations!', 400);
        }
        $depart=department::find($id);
        return $this->successResponse('success',$depart->delete());
    }

}
