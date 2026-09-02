export const team = {
    'workspace.team.heading': 'Team',
    'workspace.team.invite.email': 'Invite by email',
    'workspace.team.invite.role.owner': 'Owner',
    'workspace.team.invite.role.label': 'Role',
    // Rol DÜZELTME (`docs/83`, P1-07). Rol ADLARI yeniden kullanılır: aynı
    // rolün iki farklı etiketi olsaydı, davet ekranıyla üye listesi
    // birbirini yalanlardı.
    'workspace.team.members.role.label': 'Role for {name}',
    'workspace.team.members.role.error': 'The role could not be changed.',
    'workspace.team.invite.role.editor': 'Editor',
    // Rolün NE YAPABİLDİĞİ yazılır: "Editor" kelimesi tek başına yayınlayıp
    // yayınlayamayacağını söylemez (docs/70).
    'workspace.team.invite.role.editor.help':
        'Edits menu content. Cannot publish, change locations or see billing.',
    'workspace.team.invite.role.manager': 'Manager',
    'workspace.team.invite.role.manager.help':
        'Runs daily operations — menus, locations, QR codes and publishing. Cannot manage billing.',
    'workspace.team.invite.button': 'Invite',
    'workspace.team.invite.unavailable': 'Inviting teammates is not connected yet.',
    'workspace.team.invite.submitting': 'Submitting invitation…',
    'workspace.team.invite.error': 'Unable to create the invitation. Please try again.',
    'workspace.team.invite.success': 'Invitation created successfully.',
    'workspace.team.members.region': 'Team members',
    'workspace.team.members.loading': 'Loading team members…',
    'workspace.team.members.error': 'Team members failed to load. Please try again.',
    'workspace.team.members.empty': 'No members in this workspace yet.',
    'workspace.team.members.remove.button': 'Remove',
    'workspace.team.members.remove.confirm': 'Confirm remove',
    'workspace.team.members.remove.cancel': 'Cancel',
    'workspace.team.members.remove.busy': 'Removing…',
    'workspace.team.members.remove.error': 'Unable to remove this member. Please try again.',
    'workspace.team.members.remove.retry': 'Retry',
    'workspace.team.members.remove.success': 'Member removed.',
    'workspace.team.members.transfer.button': 'Transfer ownership',
    'workspace.team.members.transfer.title': 'Transfer workspace ownership',
    'workspace.team.members.transfer.body':
        'This member will become the workspace owner and you will become an editor. This action can be reversed later by the new owner.',
    'workspace.team.members.transfer.confirm': 'Confirm',
    'workspace.team.members.transfer.cancel': 'Cancel',
    'workspace.team.members.transfer.busy': 'Transferring…',
    'workspace.team.members.transfer.error': 'Unable to transfer ownership. Please try again.',
    'workspace.team.members.transfer.retry': 'Retry',
    'workspace.team.members.transfer.success': 'Ownership transferred.',
    'workspace.team.invitations.loading': 'Loading pending invitations…',
    'workspace.team.invitations.error': 'Pending invitations failed to load. Please try again.',
    'workspace.team.invitations.empty': 'No pending invitations.',
    'workspace.team.invitations.cancel.button': 'Cancel invitation',
    'workspace.team.invitations.cancel.confirm': 'Confirm cancel',
    'workspace.team.invitations.cancel.keep': 'Keep invitation',
    'workspace.team.invitations.cancel.busy': 'Cancelling…',
    'workspace.team.invitations.cancel.error':
        'Unable to cancel this invitation. Please try again.',
    'workspace.team.invitations.cancel.retry': 'Retry',
    'workspace.team.invitations.cancel.success': 'Invitation cancelled.',
    'workspace.team.operational.description.loading':
        'Loading pending invitations from the server-authoritative invitations list…',
    'workspace.team.operational.description.error':
        'Pending invitations failed to load — the server-authoritative invitations list could not be reached.',
    'workspace.team.operational.description.connected':
        'Current workspace members and pending invitations below come from the real server-authoritative lists.',
    'workspace.team.status.loading': 'Loading',
    'workspace.team.status.error': 'Failed to load',
    'workspace.team.status.connected': 'Invitations connected',
    'workspace.team.invite.section': 'Invite',
    'workspace.team.pendingInvitations.region': 'Pending invitations',
    'workspace.team.pendingInvitations.empty': 'Pending invitations are not available yet.',
} as const;

declare module '../workspace' {
    // eslint-disable-next-line @typescript-eslint/no-empty-object-type -- intentional declaration-merging augmentation
    interface WorkspaceTranslationCatalog extends Record<keyof typeof team, string> {}
}
