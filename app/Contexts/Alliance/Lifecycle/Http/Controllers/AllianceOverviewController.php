<?php

declare(strict_types=1);

namespace App\Contexts\Alliance\Lifecycle\Http\Controllers;

use App\Contexts\Accounts\Identity\Models\User;
use App\Contexts\Alliance\Access\Enums\AlliancePermission;
use App\Contexts\Alliance\Access\Models\Role;
use App\Contexts\Alliance\Access\Services\AllianceAuthorization;
use App\Contexts\Alliance\Content\Enums\ContentType;
use App\Contexts\Alliance\Content\Queries\ContentQuery;
use App\Contexts\Alliance\Content\Services\ContentPresenter;
use App\Contexts\Alliance\Lifecycle\Services\AllianceContext;
use App\Contexts\Alliance\Membership\Enums\AllianceRank;
use App\Contexts\Alliance\Membership\Enums\InvitationStatus;
use App\Contexts\Alliance\Membership\Enums\MembershipStatus;
use App\Contexts\Alliance\Membership\Enums\RosterState;
use App\Contexts\Alliance\Membership\Models\AllianceMembership;
use App\Contexts\Alliance\Membership\Models\AllianceRosterEntry;
use App\Contexts\Alliance\Membership\Models\Invitation;
use App\ReadModels\AllianceDashboard\AllianceDashboardCapabilitiesQuery;
use App\ReadModels\AllianceDashboard\UpcomingAllianceActivitiesQuery;
use App\Shared\Http\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class AllianceOverviewController extends Controller
{
    public function __invoke(Request $request, AllianceContext $context, AllianceAuthorization $authorization, ContentQuery $contentQuery, ContentPresenter $contentPresenter, AllianceDashboardCapabilitiesQuery $capabilitiesQuery, UpcomingAllianceActivitiesQuery $upcomingActivitiesQuery): Response
    {
        $user=$request->user(); abort_unless($user instanceof User,401);
        $actor=$context->player(); $alliance=$context->alliance()->load('kingdom'); $membership=$context->membership()->loadMissing('roles:id,alliance_id,key,name');
        $canManageInvitations=$authorization->allows($actor,$alliance,AlliancePermission::InvitationManage); $canManageMembers=$authorization->allows($actor,$alliance,AlliancePermission::MembershipManage); $canManageRoles=$authorization->allows($actor,$alliance,AlliancePermission::RoleManage); $canManageContent=$authorization->allows($actor,$alliance,AlliancePermission::ContentManage); $dashboardCapabilities=$capabilitiesQuery->for($actor,$alliance); $canManageRecruitment=$authorization->allows($actor,$alliance,AlliancePermission::RecruitmentManage); $canManageIntegrations=$authorization->allows($actor,$alliance,AlliancePermission::Manage);
        $roles=$membership->roles->map(static fn(Role $role):array=>['key'=>(string)$role->key,'name'=>(string)$role->name])->values()->all();
        $invitations=[]; $invitationCandidates=[];
        if($canManageInvitations){
            $invitations=Invitation::query()->where('alliance_id',$alliance->id)->with('player:id,current_name,game_player_id')->latest('created_at')->limit(50)->get()->map(static function(Invitation $invitation):array{$status=$invitation->status;if($status===InvitationStatus::Pending&&$invitation->expires_at?->isPast())$status=InvitationStatus::Expired;return ['id'=>(string)$invitation->id,'player'=>['id'=>(string)$invitation->player_id,'name'=>(string)$invitation->player->current_name,'gamePlayerId'=>$invitation->player->game_player_id],'email'=>(string)$invitation->email,'status'=>$status->value,'expiresAt'=>$invitation->expires_at?->toIso8601String(),'createdAt'=>$invitation->created_at?->toIso8601String()];})->values()->all();
            $activeMemberPlayerIds=AllianceMembership::query()->where('alliance_id',$alliance->id)->where('status',MembershipStatus::Active->value)->pluck('player_id');
            $invitationCandidates=AllianceRosterEntry::query()->where('alliance_id',$alliance->id)->where('state',RosterState::Active->value)->whereNotIn('player_id',$activeMemberPlayerIds)->with('player:id,current_name,game_player_id,user_id')->orderBy('observed_name')->get()->map(static fn(AllianceRosterEntry $entry):array=>['id'=>(string)$entry->player_id,'name'=>(string)$entry->player->current_name,'gamePlayerId'=>$entry->player->game_player_id,'claimed'=>$entry->player->user_id!==null])->values()->all();
        }
        $members=[]; if($canManageMembers||$canManageRoles){$members=AllianceMembership::query()->where('alliance_id',$alliance->id)->with(['player:id,current_name,game_player_id,user_id','roles:id,alliance_id,key,name'])->orderBy('created_at')->get()->map(static function(AllianceMembership $member):array{$memberRoles=$member->roles->map(static fn(Role $role):array=>['id'=>(string)$role->id,'key'=>(string)$role->key,'name'=>(string)$role->name])->values()->all();return ['id'=>(string)$member->id,'player'=>['id'=>(string)$member->player_id,'name'=>(string)$member->player->current_name,'gamePlayerId'=>$member->player->game_player_id,'claimed'=>$member->player->user_id!==null],'status'=>$member->status->value,'rank'=>$member->rank->value,'roles'=>$memberRoles];})->values()->all();}
        $roleCatalog=[]; if($canManageRoles){$roleCatalog=Role::query()->where('alliance_id',$alliance->id)->orderBy('name')->get()->map(static fn(Role $role):array=>['id'=>(string)$role->id,'key'=>(string)$role->key,'name'=>(string)$role->name])->values()->all();}
        $notices=$contentQuery->memberList($alliance,null,ContentType::Announcement->value)->take(5)->map(fn($item):array=>$contentPresenter->item($item))->values()->all();
        return Inertia::render('Alliance/Overview',[
            'user'=>['name'=>(string)$user->name,'email'=>(string)$user->email],
            'alliance'=>['id'=>$alliance->id,'name'=>$alliance->name,'slug'=>$alliance->slug,'kingdom'=>$alliance->kingdom===null?null:(string)$alliance->kingdom->number,'language'=>$alliance->language,'timezone'=>$alliance->timezone,'publicUrl'=>route('public.alliances.show',$alliance->slug)],
            'membership'=>['id'=>$membership->id,'rank'=>$membership->rank->value,'roles'=>$roles],
            'contentHub'=>['canManage'=>$canManageContent,'canManageEvents'=>$dashboardCapabilities['canManageEvents'],'canManageRecruitment'=>$canManageRecruitment,'canManageIntegrations'=>$canManageIntegrations,'notices'=>$notices,'upcomingActivities'=>$upcomingActivitiesQuery->handle($alliance)],
            'invitationManagement'=>['allowed'=>$canManageInvitations,'candidates'=>$invitationCandidates,'invitations'=>$invitations,'issuedLink'=>$request->session()->get('invitationLink')],
            'membershipManagement'=>['allowed'=>$canManageMembers,'rolesAllowed'=>$canManageRoles,'rankAllowed'=>$canManageRoles,'leadershipTransferAllowed'=>$membership->rank===AllianceRank::R5,'rankOptions'=>array_map(static fn(AllianceRank $rank):string=>$rank->value,[AllianceRank::R1,AllianceRank::R2,AllianceRank::R3,AllianceRank::R4]),'members'=>$members,'roleCatalog'=>$roleCatalog,'currentPlayerId'=>(string)$actor->id],
        ]);
    }
}
