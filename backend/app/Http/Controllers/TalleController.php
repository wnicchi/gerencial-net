<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * TalleController — ABM de Talles (tabla talles).
 * Campos: TAL_COD (código incremental), TAL_DES (descripción, máx 30, en mayúsculas).
 * En FoxPro la baja está deshabilitada, por eso no se expone destroy.
 */
class TalleController extends Controller
{
    /** @route GET /api/talles */
    public function index(): JsonResponse
    {
        return response()->json(
            DB::table('talles')->orderBy('TAL_DES')->get([
                'TAL_COD as cod',
                DB::raw('LTRIM(RTRIM(TAL_DES)) as nombre'),
            ])
        );
    }

    /** @route POST /api/talles */
    public function store(Request $request): JsonResponse
    {
        $d = $request->validate(['nombre' => 'required|string|max:30']);
        $cod = (int) DB::table('talles')->max('TAL_COD') + 1;
        DB::table('talles')->insert(['TAL_COD' => $cod, 'TAL_DES' => mb_strtoupper(trim($d['nombre']))]);
        return response()->json(['ok' => true, 'cod' => $cod], 201);
    }

    /** @route PUT /api/talles/{cod} */
    public function update(Request $request, int $cod): JsonResponse
    {
        $d = $request->validate(['nombre' => 'required|string|max:30']);
        $af = DB::table('talles')->where('TAL_COD', $cod)->update(['TAL_DES' => mb_strtoupper(trim($d['nombre']))]);
        if ($af === 0 && !DB::table('talles')->where('TAL_COD', $cod)->exists()) {
            return response()->json(['message' => 'Talle no encontrado.'], 404);
        }
        return response()->json(['ok' => true]);
    }
}
