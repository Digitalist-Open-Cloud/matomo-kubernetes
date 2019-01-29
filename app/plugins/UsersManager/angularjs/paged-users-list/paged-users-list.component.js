/*!
 * Piwik - free/libre analytics platform
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

/**
 * Usage:
 * <piwik-paged-users-list>
 */
(function () {
    angular.module('piwikApp').component('piwikPagedUsersList', {
        templateUrl: 'plugins/UsersManager/angularjs/paged-users-list/paged-users-list.component.html?cb=' + piwik.cacheBuster,
        bindings: {
            onEditUser: '&',
            onChangeUserRole: '&',
            onDeleteUser: '&',
            onSearchChange: '&',
            initialSiteId: '<',
            initialSiteName: '<',
            currentUserRole: '<',
            isLoadingUsers: '<',
            accessLevels: '<',
            filterAccessLevels: '<',
            totalEntries: '<',
            users: '<',
            searchParams: '<'
        },
        controller: PagedUsersListController
    });

    PagedUsersListController.$inject = ['$element'];

    function PagedUsersListController($element) {
        var vm = this;

        // options for selects
        vm.bulkActionAccessLevels = null;

        // selection state
        vm.areAllResultsSelected = false;
        vm.selectedRows = {};
        vm.isAllCheckboxSelected = false;

        // intermediate state
        vm.isBulkActionsDisabled = true;
        vm.userToChange = null;
        vm.roleToChangeTo = null;
        vm.previousRole = null;
        vm.accessLevelFilter = '';

        // other state
        vm.isRoleHelpToggled = false;

        vm.$onInit = $onInit;
        vm.$onChanges = $onChanges;
        vm.onAllCheckboxChange = onAllCheckboxChange;
        vm.changeUserRole = changeUserRole;
        vm.onRowSelected = onRowSelected;
        vm.deleteRequestedUsers = deleteRequestedUsers;
        vm.getPaginationUpperBound = getPaginationUpperBound;
        vm.showDeleteConfirm = showDeleteConfirm;
        vm.getAffectedUsersCount = getAffectedUsersCount;
        vm.showAccessChangeConfirm = showAccessChangeConfirm;
        vm.getRoleDisplay = getRoleDisplay;
        vm.changeSearch = changeSearch;
        vm.gotoPreviousPage = gotoPreviousPage;
        vm.gotoNextPage = gotoNextPage;

        function changeSearch(changes) {
            var newParams = $.extend({}, vm.searchParams, changes);
            vm.onSearchChange({ params: newParams });
        }

        function $onInit() {
            vm.permissionsForSite = {
                id: vm.initialSiteId,
                name: vm.initialSiteName
            };

            vm.bulkActionAccessLevels = [];
            vm.accessLevels.forEach(function (entry) {
                if (entry.key !== 'noaccess' && entry.key !== 'superuser') {
                    vm.bulkActionAccessLevels.push(entry);
                }
            });
        }

        function $onChanges(changes) {
            if (changes.users) {
                clearSelection();
            }
        }

        function onAllCheckboxChange() {
            if (!vm.isAllCheckboxSelected) {
                clearSelection();
            } else {
                for (var i = 0; i !== vm.users.length; ++i) {
                    vm.selectedRows[i] = true;
                }
                vm.isBulkActionsDisabled = false;
            }
        }

        function clearSelection() {
            vm.selectedRows = {};
            vm.areAllResultsSelected = false;
            vm.isBulkActionsDisabled = true;
            vm.isAllCheckboxSelected = false;
            vm.userToChange = null;
        }

        function changeUserRole() {
            vm.onChangeUserRole({
                users: getUserOperationSubject(),
                role: vm.roleToChangeTo
            });
        }

        function deleteRequestedUsers() {
            vm.onDeleteUser({
                users: getUserOperationSubject(),
            });
        }

        function getUserOperationSubject() {
            if (vm.userToChange) {
                return [vm.userToChange];
            } else if (vm.areAllResultsSelected) {
                return 'all';
            } else {
                return getSelectedUsers();
            }
        }

        function showAccessChangeConfirm() {
            $element.find('.change-user-role-confirm-modal').openModal({ dismissible: false });
        }

        function getAffectedUsersCount() {
            if (vm.areAllResultsSelected) {
                return vm.totalEntries;
            }

            return getSelectedCount();
        }

        function onRowSelected() {
            var selectedRowKeyCount = getSelectedCount();
            vm.isBulkActionsDisabled = selectedRowKeyCount === 0;
            vm.isAllCheckboxSelected = selectedRowKeyCount === vm.users.length;
        }

        function getSelectedCount() {
            var selectedRowKeyCount = 0;
            Object.keys(vm.selectedRows).forEach(function (key) {
                if (vm.selectedRows[key]) {
                    ++selectedRowKeyCount;
                }
            });
            return selectedRowKeyCount;
        }

        function getSelectedUsers() {
            var result = [];
            Object.keys(vm.selectedRows).forEach(function (index) {
                if (vm.selectedRows[index]
                    && vm.users[index] // sanity check
                ) {
                    result.push(vm.users[index]);
                }
            });
            return result;
        }

        function getPaginationUpperBound() {
            return Math.min(vm.searchParams.offset + vm.searchParams.limit, vm.totalEntries);
        }

        function showDeleteConfirm() {
            $element.find('.delete-user-confirm-modal').openModal({ dismissible: false });
        }

        function getRoleDisplay(role) {
            var result = null;
            vm.accessLevels.forEach(function (entry) {
                if (entry.key === role) {
                    result = entry.value;
                }
            });
            return result;
        }

        function gotoPreviousPage() {
            changeSearch({
                offset: Math.max(0, vm.searchParams.offset - vm.searchParams.limit)
            });
        }

        function gotoNextPage() {
            var newOffset = vm.searchParams.offset + vm.searchParams.limit;
            if (newOffset >= vm.totalEntries) {
                return;
            }

            changeSearch({
                offset: newOffset,
            });
        }
    }
})();
