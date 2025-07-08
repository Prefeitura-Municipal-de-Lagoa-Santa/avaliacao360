import { computed } from 'vue'
import { usePage } from '@inertiajs/vue3'
import { User } from '@/types'

export function usePermissions() {
  const page = usePage<{ auth: { user: User | null } }>()
  const user = computed(() => page.props.auth.user)

  /**
   * Verifica se o usuário possui a role informada
   * Se o usuário for Admin, retorna true para qualquer role
   */
  const hasRole = (roleName: string): boolean => {
    if (!user.value) return false
    if (user.value.roles?.some(r => r.name === 'Admin')) return true
    return user.value.roles?.some(r => r.name === roleName) ?? false
  }

  /**
   * Agrega permissões diretas + permissões via roles, removendo duplicados
   */
  const allPermissions = computed(() => {
    if (!user.value) return []
    const direct = user.value.permissions.map(p => p.name)
    const viaRoles = user.value.roles.flatMap(r => r.permissions.map(p => p.name))
    return Array.from(new Set([...direct, ...viaRoles]))
  })

  /**
   * Retorna true se o usuário tiver a permissão (direta ou via role) ou for Admin
   */
  const can = (permission: string): boolean => {
    if (hasRole('Admin')) return true
    return allPermissions.value.includes(permission)
  }

  return { user, hasRole, can, allPermissions }
}
