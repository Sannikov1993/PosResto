const fs = require('fs');
let content = fs.readFileSync('FloorTable.vue', 'utf8');

// Fix surfaceClasses
const surfaceOld = `const surfaceClasses = computed(() => {    const classes = [];    if (props.isInLinkedGroup) {        classes.push('table-occupied');    } else if (props.isFloorDateToday && props.table.active_orders_total > 0) {        // Есть заказ - стол занят        classes.push('table-occupied');    } else if (props.isFloorDateToday && props.table.next_reservation && props.table.next_reservation.status === 'seated') {        classes.push('table-occupied');    } else if (props.isFloorDateToday && props.table.next_reservation) {        classes.push('table-free');    } else if (props.table.next_reservation) {        classes.push('table-reserved');    } else if (props.isFloorDateToday) {        classes.push('table-' + (props.table.status || 'free'));    } else {        classes.push('table-free');    }`;

const surfaceNew = `const surfaceClasses = computed(() => {
    const classes = [];

    if (props.isInLinkedGroup) {
        classes.push('table-occupied');
    } else if (props.isFloorDateToday && props.table.active_orders_total > 0) {
        classes.push('table-occupied');
    } else if (props.isFloorDateToday && props.table.next_reservation && props.table.next_reservation.status === 'seated') {
        classes.push('table-occupied');
    } else if (props.isFloorDateToday && props.table.next_reservation) {
        classes.push('table-free');
    } else if (props.table.next_reservation) {
        classes.push('table-reserved');
    } else if (props.isFloorDateToday) {
        classes.push('table-' + (props.table.status || 'free'));
    } else {
        classes.push('table-free');
    }`;

content = content.replace(surfaceOld, surfaceNew);

// Fix chairClass
const chairOld = `const chairClass = computed(() => {    const classes = [];    if (props.isInLinkedGroup) {        classes.push('chair-occupied');    } else if (props.isFloorDateToday && props.table.active_orders_total > 0) {        // Есть заказ - стулья как занятые        classes.push('chair-occupied');    } else if (props.isFloorDateToday && props.table.next_reservation && props.table.next_reservation.status === 'seated') {        classes.push('chair-occupied');    } else if (props.isFloorDateToday && props.table.next_reservation) {        classes.push('chair-free');    } else if (props.table.next_reservation) {        classes.push('chair-reserved');    } else if (props.isFloorDateToday) {        classes.push('chair-' + (props.table.status || 'free'));    } else {        classes.push('chair-free');    }`;

const chairNew = `const chairClass = computed(() => {
    const classes = [];

    if (props.isInLinkedGroup) {
        classes.push('chair-occupied');
    } else if (props.isFloorDateToday && props.table.active_orders_total > 0) {
        classes.push('chair-occupied');
    } else if (props.isFloorDateToday && props.table.next_reservation && props.table.next_reservation.status === 'seated') {
        classes.push('chair-occupied');
    } else if (props.isFloorDateToday && props.table.next_reservation) {
        classes.push('chair-free');
    } else if (props.table.next_reservation) {
        classes.push('chair-reserved');
    } else if (props.isFloorDateToday) {
        classes.push('chair-' + (props.table.status || 'free'));
    } else {
        classes.push('chair-free');
    }`;

content = content.replace(chairOld, chairNew);

fs.writeFileSync('FloorTable.vue', content);
// Done
