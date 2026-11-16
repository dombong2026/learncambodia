<?php

namespace App\Http\Controllers;

use App\Models\SupportTicket;
use App\Models\SupportMessage;
use App\Models\EditorMedia;
use App\Support\EditorHtml;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

final class SupportTicketController extends Controller
{
    private function authorizeTicket(Request $request, SupportTicket $ticket): void
    {
        abort_unless($request->user()->isAdministrator() || $ticket->user_id === $request->user()->id, 403);
    }

    private function ticketKind(string $kind): string
    {
        abort_unless(in_array($kind, ['tickets','chats'], true), 404);
        return $kind === 'tickets' ? 'ticket' : 'chat';
    }

    public function index(Request $request): View
    {
        $validated = $request->validate(['kind'=>['nullable',Rule::in(['ticket','chat'])]]);
        $kind = $validated['kind'] ?? 'ticket';
        $tickets = SupportTicket::where('user_id',$request->user()->id)
            ->where('kind',$kind)->orderByDesc('updated_at')->paginate(10)->withQueryString();
        return view('support.tickets-index',compact('tickets','kind'));
    }

    public function adminIndex(Request $request, string $kind): View
    {
        $type = $this->ticketKind($kind);
        $data = $request->validate([
            'status'=>['nullable',Rule::in(['open','in_progress','resolved','closed'])],
            'q'=>['nullable','string','max:80'],
        ]);
        $query = trim((string)($data['q']??''));
        $status = $data['status']??'';
        $tickets = SupportTicket::with('owner:id,name,email')->withCount('messages')
            ->where('kind',$type)
            ->when($status!=='',fn($b)=>$b->where('status',$status))
            ->when($query!=='',fn($b)=>$b->where(function($q)use($query){
                $q->where('subject','like','%'.$query.'%')->orWhereHas('owner',
                    fn($owners)=>$owners->where('name','like','%'.$query.'%'));
            }))
            ->orderByDesc('updated_at')->paginate(10)->withQueryString();
        return view('workspace.admin.support-index',compact('tickets','kind','query','status'));
    }

    public function create(): View
    {
        return view('support.ticket-form');
    }

    public function store(Request $request): RedirectResponse
    {
        $html = EditorHtml::clean($request->input('body_html'));
        if ($html !== null && trim((string) $request->input('body', '')) === '') {
            $request->merge(['body' => EditorHtml::text($html)]);
        }
        $data = $request->validate([
            'subject'=>['required','string','min:3','max:160'],
            'priority'=>['required',Rule::in(['low','normal','high'])],
            'body'=>['required','string','min:3','max:4000'],
            'body_html'=>['nullable','string','max:24000'],
        ]);
        $data['body_html'] = $html;
        $ticket = DB::transaction(function() use($request,$data): SupportTicket {
            $ticket = SupportTicket::create([
                'user_id'=>$request->user()->id,
                'kind'=>'ticket',
                'subject'=>$data['subject'],
                'priority'=>$data['priority'],
                'status'=>'open',
            ]);
            EditorMedia::claim($data['body_html'], $request->user(), 'ticket', $ticket->id);
            $ticket->messages()->create(['user_id'=>$request->user()->id,'body'=>$data['body'],'body_html'=>$data['body_html']]);
            return $ticket;
        });
        return redirect()->route('support.tickets.show',$ticket)->with('status','Ticket created.');
    }

    public function startChat(Request $request): RedirectResponse
    {
        // Serialize chat creation per account to avoid two simultaneous active chats.
        $chat = DB::transaction(function () use ($request): SupportTicket {
            \App\Models\User::query()->whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
            $active = SupportTicket::where('user_id',$request->user()->id)
                ->where('kind','chat')->whereNotIn('status',['closed','resolved'])
                ->orderByDesc('id')->first();
            return $active ?? SupportTicket::create([
                'user_id'=>$request->user()->id,'kind'=>'chat',
                'subject'=>'Live chat','priority'=>'normal','status'=>'open',
            ]);
        });
        return redirect()->route('support.tickets.show',$chat);
    }

    public function show(Request $request, SupportTicket $ticket): View
    {
        $this->authorizeTicket($request,$ticket);
        $messages=$ticket->messages()->with('author:id,name')->orderByDesc('id')
            ->paginate(50)->withQueryString();
        $messages->setCollection($messages->getCollection()->reverse()->values());
        return view('support.ticket-show',compact('ticket','messages'));
    }

    public function reply(Request $request, SupportTicket $ticket): RedirectResponse
    {
        $this->authorizeTicket($request,$ticket);
        $html = EditorHtml::clean($request->input('body_html'));
        if ($html !== null && trim((string) $request->input('body', '')) === '') {
            $request->merge(['body' => EditorHtml::text($html)]);
        }
        $data=$request->validate([
            'body'=>['required','string','min:1','max:4000'],
            'body_html'=>['nullable','string','max:24000'],
        ]);
        $data['body_html'] = $html;
        DB::transaction(function() use($request,$ticket,$data): void {
            $locked=SupportTicket::whereKey($ticket->id)->lockForUpdate()->firstOrFail();
            abort_if($locked->status==='closed',409,'Closed conversations cannot receive messages.');
            EditorMedia::claim($data['body_html'], $request->user(), 'ticket', $locked->id);
            $locked->messages()->create(['user_id'=>$request->user()->id,'body'=>$data['body'],'body_html'=>$data['body_html']]);
            if ($request->user()->role==='admin' && $locked->status==='open')
                $locked->status='in_progress';
            if ($request->user()->role!=='admin' && $locked->status==='resolved')
                $locked->status='open';
            $locked->touch();
            $locked->save();
        });
        return back()->with('status','Message sent.');
    }

    public function messages(Request $request, SupportTicket $ticket): JsonResponse
    {
        $this->authorizeTicket($request,$ticket);
        $data=$request->validate(['after'=>['nullable','integer','min:0']]);
        $messages=$ticket->messages()->with('author:id,name')
            ->where('id','>',(int)($data['after']??0))
            ->orderBy('id')->limit(100)->get()->map(fn (SupportMessage $message)=>[
                'id'=>$message->id,
                'author'=>$message->author?->name ?? 'Account',
                'own'=>$message->user_id === $request->user()->id,
                'body'=>$message->body,
                'body_html'=>EditorHtml::clean($message->body_html),
                'at'=>$message->created_at?->format('Y-m-d H:i'),
            ]);
        return response()->json(['messages'=>$messages,'status'=>$ticket->status]);
    }

    public function update(Request $request, SupportTicket $ticket): RedirectResponse
    {
        $this->authorizeTicket($request,$ticket);
        abort_unless($ticket->user_id===$request->user()->id,403);
        abort_unless($ticket->kind==='ticket',404);
        $data=$request->validate([
            'subject'=>['required','string','min:3','max:160'],
            'priority'=>['required',Rule::in(['low','normal','high'])],
            'status'=>['required',Rule::in(['open','closed'])],
        ]);
        $ticket->update($data);
        return back()->with('status','Ticket updated.');
    }

    public function destroy(Request $request, SupportTicket $ticket): RedirectResponse
    {
        $this->authorizeTicket($request,$ticket);
        abort_unless($ticket->user_id===$request->user()->id,403);
        $ticket->delete();
        return redirect()->route('support.tickets.index',['kind'=>$ticket->kind])
            ->with('status','Conversation removed from your account.');
    }

    public function adminUpdate(Request $request, string $kind, SupportTicket $ticket): RedirectResponse
    {
        abort_unless($ticket->kind===$this->ticketKind($kind),404);
        $data=$request->validate([
            'status'=>['required',Rule::in(['open','in_progress','resolved','closed'])],
            'priority'=>['required',Rule::in(['low','normal','high'])],
        ]);
        $ticket->update($data+['assigned_to'=>$request->user()->id]);
        return back()->with('status','Support conversation updated.');
    }

    public function adminDestroy(string $kind, SupportTicket $ticket): RedirectResponse
    {
        abort_unless($ticket->kind===$this->ticketKind($kind),404);
        $ticket->delete();
        return back()->with('status','Support conversation archived.');
    }
}
