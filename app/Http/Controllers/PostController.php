<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Requests\PostRequest;
use App\Models\Post;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class PostController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $posts = Post::with('user')->orderBy('created_at', 'desc')->simplePaginate(4);
        return view('posts.index', compact('posts'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        return view('posts.create');
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \App\Http\Requests\PostRequest  $request
     * @return \Illuminate\Http\Response
     */
    public function store(PostRequest $request)
    {
        $post = new Post($request->all());
        $post->user_id = $request->user()->id;      // Auth()->user()->id、Auth::id() でもOK！

        $file = $request->file('image');
        $post->image = self::createFileName($file);     // 下の方で共通化したクラスメソッド


        // トランザクションの開始
        DB::beginTransaction();
        try {
            // 画像の登録
            $post->save();

            //  画像のアップロード
            if (!Storage::putFileAs('images/posts', $file, $post->image)) {
                // 例外を発生させてロールバックする
                throw new \Exception('画像ファイルの保存に失敗しました。');
            }

            // トランザクションの終了
            DB::commit();
        } catch (\Exception $e) {
            // トランザクションの失敗(終了)
            DB::rollback();
            return back()->withInput()->withErrors($e->getMessage());
        }

        return redirect()
            ->route('posts.show', $post)
            ->with('notice', '記事を登録しました');     // フラッシュメッセージ（セッションを利用してリダイレクト先にメッセージを渡す)：with('メッセージのkey', 'メッセージの値')
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $post = Post::with(['user'])->find($id);
        $comments = $post->comments()->latest()->get()->load(['user']);

        return view('posts.show', compact('post', 'comments'));
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        $post = Post::find($id);
        return view('posts.edit', compact('post'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \App\Http\Requests\PostRequest  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(PostRequest $request, $id)
    {
        $post = Post::find($id);        // $post と $delete_file_path  は古い方の画像、
                                        // $request と $file  は新しい方の画像

        if ($request->user()->cannot('update', $post)) {
            return redirect()->route('posts.show', $post)
                ->withErrors('自分の記事以外は更新できません');
        }

        $file = $request->file('image');
        if ($file) {
            $delete_file_path = $post->image_path;
            $post->image = self::createFileName($file);       // 代入して上書き　　// 下の方で共通化したクラスメソッド
        }
        $post->fill($request->all());       // 代入して上書き

        // トランザクション開始
        DB::beginTransaction();
        try {
            // 更新
            $post->save();      // 上書き保存（以降は $post が新しいほうの画像）

            if ($file) {
                // 新しい方の画像をアップロード
                if (!Storage::putFileAs('images/posts', $file, $post->image)) {
                    // 例外を投げてロールバックさせる
                    throw new \Exception('画像ファイルの保存に失敗しました。');
                }
                // 古いほうの画像を削除
                if (!Storage::delete($delete_file_path)) {      // 古いほうの画像を消せなかった場合の処理が以下
                    // 古いほうを消せなかったので、アップロードした新しい方を削除する
                    Storage::delete($post->image_path);
                    //例外を投げてロールバックさせる
                    throw new \Exception('画像ファイルの削除に失敗しました。');
                }
            }

            // トランザクション終了(成功)
            DB::commit();
        } catch (\Exception $e) {
            // トランザクション終了(失敗)
            DB::rollback();
            return back()->withInput()->withErrors($e->getMessage());
        }

        return redirect()->route('posts.show', $post)
            ->with('notice', '記事を更新しました');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $post = Post::find($id);

        // トランザクション開始
        DB::beginTransaction();
        try {
            $post->delete();

            // 画像削除
            if (!Storage::delete($post->image_path)) {
                // 例外を投げてロールバックさせる
                throw new \Exception('画像ファイルの削除に失敗しました。');
            }

            // トランザクション終了(成功)
            DB::commit();
        } catch (\Exception $e) {
            // トランザクション終了(失敗)
            DB::rollback();
            return back()->withInput()->withErrors($e->getMessage());
        }

        return redirect()->route('posts.index')
            ->with('notice', '記事を削除しました');
    }


    private static function createFileName($file){
        return date('YmdHis') . '_' . $file->getClientOriginalName();
    }
}
