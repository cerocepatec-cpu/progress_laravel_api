<?php

namespace App\Http\Controllers;

use App\Models\affectation_users;
use App\Models\User;
use App\Http\Requests\Storeaffectation_usersRequest;
use App\Http\Requests\Updateaffectation_usersRequest;
use App\Models\department;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AffectationUsersController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        try {
            $departement_id = $request->query('departement_id');

            if (!$departement_id) {
                return $this->errorResponse('departement required');
            }
            $affectations=affectation_users::where('department_id', $departement_id)->get();
            $listdata = $affectations->map(function ($item) {
                return $this->show($item);
            });
            return $this->successResponse('success', $listdata);
        } catch (\Exception $e) {
            return $this->errorResponse('error');
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
        try {
            // Cas 1 : plusieurs agents envoyés
            if (!empty($request['agents']) && count($request['agents']) > 0) {
                $depart = department::findOrFail($request['department']['id']);

                $results = collect($request['agents'])
                    ->filter()
                    ->map(function ($agent) use ($depart) {
                        // Vérifier si déjà affecté à ce département
                        $exists = affectation_users::where('user_id', $agent['id'])
                            ->where('department_id', $depart->id)
                            ->exists();

                        if ($exists) {
                            return [
                                'status'  => 'skipped',
                                'message' => "L'agent {$agent['id']} est déjà affecté à ce département",
                            ];
                        }

                        $affectation = affectation_users::create([
                            'level'         => $agent['level'] ?? 'simple',
                            'user_id'       => $agent['id'],
                            'department_id' => $depart->id,
                        ]);

                        return [
                            'status' => 'created',
                            'data'   => $this->show($affectation),
                        ];
                    });

                return $this->successResponse('success', $results);
            }

            // Cas 2 : un seul agent avec level "chief"
            if ($request->level === 'chief') {
                DB::update(
                    'UPDATE affectation_users SET level = ? WHERE department_id = ?',
                    ['simple', $request->department_id]
                );
            }

            // Vérifier doublon pour un seul agent
            $exists = affectation_users::where('user_id', $request->user_id)
                ->where('department_id', $request->department_id)
                ->exists();

            if ($exists) {
                return $this->errorResponse('duplicated');
            }

            // Création simple si pas d'agents multiples
            $affected = affectation_users::create($request->all());

            return $this->successResponse('success', $this->show($affected));

        } catch (\Exception $e) {
            return $this->errorResponse('error',500);
        }
    }


    /**
     * Display the specified resource.
     *
     * @param  \App\Models\affectation_users  $affectation_users
     * @return \Illuminate\Http\Response
     */
    public function show(affectation_users $affectation_users)
    {
        $affectation=User::leftjoin('affectation_users as A', 'users.id','=','A.user_id')
        ->leftjoin('departments as D', 'A.department_id','=','D.id')
        ->where('users.id', '=',$affectation_users->user_id)
        ->get(['D.department_name as department_name', 'D.id as department_id', 'users.*', 'A.level','A.id as affectation_depart_id'])->first();
        return $affectation;
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\affectation_users  $affectation_users
     * @return \Illuminate\Http\Response
     */
    public function edit(affectation_users $affectation_users)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \App\Http\Requests\Updateaffectation_usersRequest  $request
     * @param  \App\Models\affectation_users  $affectation_users
     * @return \Illuminate\Http\Response
     */
    public function update(Updateaffectation_usersRequest $request, affectation_users $affectation_users)
    {
        if ($request->level === 'chief') {
            DB::update(
                'UPDATE affectation_users SET level = ? WHERE department_id = ?',
                ['simple', $request->department_id]
            );
        }
        $element = affectation_users::find($request['id']);
        $element->update($request->all());
        return $this->show($element);
    }

    public function update2(Request $request){

        if($request->level=='chief'){
          DB::update('update affectation_users set level = ? where department_id = ? ',['simple',$request->department_id]);
        }

        $affectation=affectation_users::where('user_id','=',$request->user_id)->get()->first();
        $affectation->update($request->all());

        return $this->show($affectation);
    }
    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\affectation_users  $affectation_users
     * @return \Illuminate\Http\Response
     */
   public function destroy(affectation_users $affectation_users)
    {
        try {
            DB::beginTransaction();
            // Suppression de l'affectation
            $deleted = affectation_users::destroy($affectation_users->id);

            if (!$deleted) {
                DB::rollBack();
                return $this->errorResponse("error", 400);
            }

            DB::commit();
            return $this->successResponse("success", $deleted);

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function destroy2($id)
    {
        $affectation=affectation_users::find($id);
        return $affectation->delete();
    }

    public function reference(){
        $uploaddir = "uploads/";
        $uploadfile = $uploaddir.basename($_FILES['filekey']['name']);
        $uploaded = move_uploaded_file($_FILES['filekey']['tmp_name'],$uploadfile);

        if ($uploaded) {
            echo "uploaded successfully";
        }else{
            echo "error on uploading";
        }
    }

}
