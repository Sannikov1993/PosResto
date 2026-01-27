<div class="bg-dark-800 rounded-2xl p-6 border border-gray-700">
    <div class="text-center mb-6">
        <div class="w-20 h-20 bg-yellow-500/20 rounded-2xl flex items-center justify-center mx-auto mb-4">
            <span class="text-4xl">📅</span>
        </div>
        <h2 class="text-2xl font-bold text-white">Бронирование стола {{ $table->number }}</h2>
        <p class="text-gray-400 mt-1">{{ $table->seats }} мест</p>
    </div>

    <div class="space-y-4">
        <!-- Календарь -->
        <div class="bg-dark-900 rounded-xl p-4">
            <div class="flex items-center justify-between mb-3">
                <button onclick="changeBookingMonth(-1)" class="w-8 h-8 bg-dark-800 rounded-lg text-gray-400 hover:text-white hover:bg-gray-700 btn">◀</button>
                <span id="bookingMonthName" class="text-white font-medium"></span>
                <button onclick="changeBookingMonth(1)" class="w-8 h-8 bg-dark-800 rounded-lg text-gray-400 hover:text-white hover:bg-gray-700 btn">▶</button>
            </div>

            <div class="grid grid-cols-7 gap-1 mb-2">
                <div class="text-center text-xs text-gray-500 py-1">Пн</div>
                <div class="text-center text-xs text-gray-500 py-1">Вт</div>
                <div class="text-center text-xs text-gray-500 py-1">Ср</div>
                <div class="text-center text-xs text-gray-500 py-1">Чт</div>
                <div class="text-center text-xs text-gray-500 py-1">Пт</div>
                <div class="text-center text-xs text-gray-500 py-1">Сб</div>
                <div class="text-center text-xs text-gray-500 py-1">Вс</div>
            </div>

            <div id="bookingCalendar" class="grid grid-cols-7 gap-1">
                <!-- Заполняется через JS -->
            </div>
        </div>

        <!-- Выбранная дата -->
        <div class="bg-dark-900 rounded-xl p-4">
            <p class="text-gray-400 text-xs mb-1">Выбранная дата</p>
            <p id="selectedDateText" class="text-white font-bold text-lg"></p>
        </div>

        <!-- Время -->
        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block text-gray-400 text-sm mb-2">Время начала</label>
                <select id="bookingTime" onchange="updateBookingPreview()"
                        class="w-full bg-dark-900 border border-gray-700 rounded-lg px-4 py-3 text-white focus:outline-none focus:border-accent">
                    @for($h = 10; $h < 22; $h++)
                        @for($m = 0; $m < 60; $m += 30)
                            <option value="{{ sprintf('%02d:%02d', $h, $m) }}" {{ $h == 19 && $m == 0 ? 'selected' : '' }}>
                                {{ sprintf('%02d:%02d', $h, $m) }}
                            </option>
                        @endfor
                    @endfor
                </select>
            </div>
            <div>
                <label class="block text-gray-400 text-sm mb-2">Длительность</label>
                <select id="bookingDuration" onchange="updateBookingPreview()"
                        class="w-full bg-dark-900 border border-gray-700 rounded-lg px-4 py-3 text-white focus:outline-none focus:border-accent">
                    <option value="60">1 час</option>
                    <option value="90">1.5 часа</option>
                    <option value="120" selected>2 часа</option>
                    <option value="150">2.5 часа</option>
                    <option value="180">3 часа</option>
                    <option value="240">4 часа</option>
                </select>
            </div>
        </div>

        <!-- Таймлайн -->
        <div class="bg-dark-900 rounded-xl p-4">
            <p class="text-gray-400 text-xs mb-3">Занятость стола</p>
            <div class="relative">
                <div class="flex justify-between text-xs text-gray-500 mb-1">
                    @for($h = 10; $h <= 22; $h++)
                        <span>{{ $h }}</span>
                    @endfor
                </div>
                <div class="relative h-10 bg-dark-800 rounded-lg overflow-hidden">
                    <div class="absolute inset-0 flex">
                        @for($h = 10; $h < 22; $h++)
                            <div class="flex-1 border-r border-gray-700/50"></div>
                        @endfor
                    </div>
                    <div id="existingReservations">
                        <!-- Существующие брони -->
                    </div>
                    <div id="newReservationPreview" class="absolute top-1 bottom-1 bg-yellow-500/30 border-2 border-dashed border-yellow-400 rounded-md hidden">
                        <!-- Превью новой брони -->
                    </div>
                </div>
            </div>
        </div>

        <!-- Количество гостей -->
        <div class="bg-dark-900 rounded-xl p-4">
            <p class="text-gray-400 text-xs mb-2">Количество гостей</p>
            <div class="flex items-center justify-between">
                <button onclick="changeBookingGuests(-1)" class="w-10 h-10 bg-dark-800 rounded-lg text-white hover:bg-gray-700 btn text-xl">−</button>
                <span id="bookingGuests" class="text-3xl font-bold text-white">2</span>
                <button onclick="changeBookingGuests(1)" class="w-10 h-10 bg-dark-800 rounded-lg text-white hover:bg-gray-700 btn text-xl">+</button>
            </div>
            <p class="text-gray-500 text-xs text-center mt-2">макс. {{ $table->seats }} мест</p>
        </div>

        <!-- Информация о госте -->
        <div class="bg-dark-900 rounded-xl p-4 space-y-3">
            <p class="text-gray-400 text-sm font-medium flex items-center gap-2">
                <span>👤</span> Информация о госте
            </p>
            <div class="grid grid-cols-2 gap-3">
                <input type="text" id="bookingGuestName" placeholder="Имя гостя *"
                       class="bg-dark-800 border border-gray-700 rounded-lg px-4 py-3 text-white focus:outline-none focus:border-accent">
                <input type="tel" id="bookingGuestPhone" placeholder="Телефон *"
                       class="bg-dark-800 border border-gray-700 rounded-lg px-4 py-3 text-white focus:outline-none focus:border-accent">
            </div>
            <input type="email" id="bookingGuestEmail" placeholder="Email (необязательно)"
                   class="w-full bg-dark-800 border border-gray-700 rounded-lg px-4 py-3 text-white focus:outline-none focus:border-accent">
        </div>

        <!-- Депозит -->
        <div class="bg-dark-900 rounded-xl p-4 space-y-3">
            <p class="text-gray-400 text-sm font-medium flex items-center gap-2">
                <span>💰</span> Депозит
            </p>
            <div class="grid grid-cols-4 gap-2">
                <button onclick="setDeposit(0)" class="deposit-btn py-2.5 rounded-lg text-sm font-medium btn bg-yellow-500 text-black" data-amount="0">Нет</button>
                <button onclick="setDeposit(1000)" class="deposit-btn py-2.5 rounded-lg text-sm font-medium btn bg-dark-800 text-gray-400 hover:bg-gray-700" data-amount="1000">1000 ₽</button>
                <button onclick="setDeposit(2000)" class="deposit-btn py-2.5 rounded-lg text-sm font-medium btn bg-dark-800 text-gray-400 hover:bg-gray-700" data-amount="2000">2000 ₽</button>
                <button onclick="setDeposit(5000)" class="deposit-btn py-2.5 rounded-lg text-sm font-medium btn bg-dark-800 text-gray-400 hover:bg-gray-700" data-amount="5000">5000 ₽</button>
            </div>
        </div>

        <!-- Примечания -->
        <div class="bg-dark-900 rounded-xl p-4">
            <textarea id="bookingNotes" placeholder="Комментарий к бронированию..."
                      class="w-full bg-dark-800 border border-gray-700 rounded-lg px-4 py-3 text-white focus:outline-none focus:border-accent resize-none h-20"></textarea>
        </div>

        <!-- Кнопка создания -->
        <button onclick="submitBooking()" id="submitBookingBtn"
                class="w-full py-4 bg-yellow-500 hover:bg-yellow-400 text-black rounded-xl font-bold text-lg btn shadow-lg shadow-yellow-500/20">
            📅 Создать бронь
        </button>
    </div>
</div>

<script>
    // ========== СОСТОЯНИЕ БРОНИРОВАНИЯ ==========
    const bookingState = {
        tableId: {{ $table->id }},
        maxSeats: {{ $table->seats }},
        date: new Date().toISOString().split('T')[0],
        time: '19:00',
        duration: 120,
        guests: 2,
        deposit: 0,
        calendarMonth: new Date(),
        existingReservations: []
    };

    // ========== ИНИЦИАЛИЗАЦИЯ ==========
    document.addEventListener('DOMContentLoaded', () => {
        renderBookingCalendar();
        updateSelectedDateText();
        updateBookingPreview();
        loadExistingReservations();
    });

    // ========== КАЛЕНДАРЬ ==========
    function renderBookingCalendar() {
        const year = bookingState.calendarMonth.getFullYear();
        const month = bookingState.calendarMonth.getMonth();
        const firstDay = new Date(year, month, 1);
        const lastDay = new Date(year, month + 1, 0);
        const today = new Date().toISOString().split('T')[0];

        // Название месяца
        document.getElementById('bookingMonthName').textContent =
            bookingState.calendarMonth.toLocaleDateString('ru-RU', { month: 'long', year: 'numeric' });

        // Дни
        let html = '';
        const startPadding = firstDay.getDay() === 0 ? 6 : firstDay.getDay() - 1;

        for (let i = 0; i < startPadding; i++) {
            html += '<div></div>';
        }

        for (let d = 1; d <= lastDay.getDate(); d++) {
            const date = new Date(year, month, d);
            const dateStr = date.toISOString().split('T')[0];
            const isPast = dateStr < today;
            const isSelected = dateStr === bookingState.date;
            const isToday = dateStr === today;

            let classes = 'w-full aspect-square rounded-lg text-sm font-medium btn flex items-center justify-center ';
            if (isPast) {
                classes += 'text-gray-600 cursor-not-allowed';
            } else if (isSelected) {
                classes += 'bg-yellow-500 text-black';
            } else if (isToday) {
                classes += 'bg-accent/20 text-accent ring-1 ring-accent hover:bg-accent/30';
            } else {
                classes += 'text-gray-300 hover:bg-gray-700';
            }

            html += `<button onclick="${isPast ? '' : `selectBookingDate('${dateStr}')`}" class="${classes}" ${isPast ? 'disabled' : ''}>${d}</button>`;
        }

        document.getElementById('bookingCalendar').innerHTML = html;
    }

    function changeBookingMonth(delta) {
        bookingState.calendarMonth.setMonth(bookingState.calendarMonth.getMonth() + delta);
        renderBookingCalendar();
    }

    function selectBookingDate(dateStr) {
        bookingState.date = dateStr;
        renderBookingCalendar();
        updateSelectedDateText();
        loadExistingReservations();
    }

    function updateSelectedDateText() {
        const date = new Date(bookingState.date);
        document.getElementById('selectedDateText').textContent =
            date.toLocaleDateString('ru-RU', { weekday: 'long', day: 'numeric', month: 'long' });
    }

    // ========== ТАЙМЛАЙН ==========
    async function loadExistingReservations() {
        try {
            const response = await axios.get(`/api/reservations`, {
                params: {
                    table_id: bookingState.tableId,
                    date: bookingState.date
                }
            });
            bookingState.existingReservations = response.data.data || [];
            renderExistingReservations();
        } catch (error) {
            console.error('Ошибка загрузки бронирований:', error);
        }
    }

    function renderExistingReservations() {
        const container = document.getElementById('existingReservations');
        container.innerHTML = bookingState.existingReservations.map(res => {
            const style = getTimelineStyle(res.time_from, res.time_to);
            return `<div class="absolute top-1 bottom-1 bg-red-500/40 border border-red-500 rounded-md flex items-center justify-center overflow-hidden" style="left:${style.left};width:${style.width}">
                <span class="text-xs text-red-200 truncate px-1">${res.guest_name}</span>
            </div>`;
        }).join('');
    }

    function getTimelineStyle(timeFrom, timeTo) {
        const [h1, m1] = timeFrom.split(':').map(Number);
        const [h2, m2] = timeTo.split(':').map(Number);
        const startMinutes = h1 * 60 + m1;
        const endMinutes = h2 * 60 + m2;
        const timelineStart = 10 * 60;
        const timelineEnd = 22 * 60;
        const totalMinutes = timelineEnd - timelineStart;

        const left = ((startMinutes - timelineStart) / totalMinutes) * 100;
        const width = ((endMinutes - startMinutes) / totalMinutes) * 100;

        return {
            left: Math.max(0, left) + '%',
            width: Math.min(100 - left, width) + '%'
        };
    }

    function updateBookingPreview() {
        bookingState.time = document.getElementById('bookingTime').value;
        bookingState.duration = parseInt(document.getElementById('bookingDuration').value);

        const [h, m] = bookingState.time.split(':').map(Number);
        const endMinutes = h * 60 + m + bookingState.duration;
        const endH = Math.floor(endMinutes / 60);
        const endM = endMinutes % 60;
        const timeTo = `${String(endH).padStart(2, '0')}:${String(endM).padStart(2, '0')}`;

        const preview = document.getElementById('newReservationPreview');
        const style = getTimelineStyle(bookingState.time, timeTo);
        preview.style.left = style.left;
        preview.style.width = style.width;
        preview.classList.remove('hidden');
    }

    // ========== ГОСТИ ==========
    function changeBookingGuests(delta) {
        bookingState.guests = Math.max(1, Math.min(bookingState.maxSeats, bookingState.guests + delta));
        document.getElementById('bookingGuests').textContent = bookingState.guests;
    }

    // ========== ДЕПОЗИТ ==========
    function setDeposit(amount) {
        bookingState.deposit = amount;
        document.querySelectorAll('.deposit-btn').forEach(btn => {
            if (parseInt(btn.dataset.amount) === amount) {
                btn.classList.add('bg-yellow-500', 'text-black');
                btn.classList.remove('bg-dark-800', 'text-gray-400');
            } else {
                btn.classList.remove('bg-yellow-500', 'text-black');
                btn.classList.add('bg-dark-800', 'text-gray-400');
            }
        });
    }

    // ========== ОТПРАВКА ==========
    async function submitBooking() {
        const guestName = document.getElementById('bookingGuestName').value.trim();
        const guestPhone = document.getElementById('bookingGuestPhone').value.trim();

        if (!guestName || !guestPhone) {
            showToast('Заполните имя и телефон гостя', 'error');
            return;
        }

        const [h, m] = bookingState.time.split(':').map(Number);
        const endMinutes = h * 60 + m + bookingState.duration;
        const endH = Math.floor(endMinutes / 60);
        const endM = endMinutes % 60;
        const timeTo = `${String(endH).padStart(2, '0')}:${String(endM).padStart(2, '0')}`;

        const data = {
            table_id: bookingState.tableId,
            guest_name: guestName,
            guest_phone: guestPhone,
            guest_email: document.getElementById('bookingGuestEmail').value.trim() || null,
            date: bookingState.date,
            time_from: bookingState.time,
            time_to: timeTo,
            guests_count: bookingState.guests,
            deposit: bookingState.deposit,
            notes: document.getElementById('bookingNotes').value.trim() || null
        };

        try {
            const response = await axios.post('/api/reservations', data);
            if (response.data.success) {
                showToast('Бронирование создано!', 'success');
                // Очистить форму
                document.getElementById('bookingGuestName').value = '';
                document.getElementById('bookingGuestPhone').value = '';
                document.getElementById('bookingGuestEmail').value = '';
                document.getElementById('bookingNotes').value = '';
                loadExistingReservations();
            } else {
                showToast(response.data.message || 'Ошибка создания брони', 'error');
            }
        } catch (error) {
            showToast(error.response?.data?.message || 'Ошибка соединения', 'error');
        }
    }
</script>
